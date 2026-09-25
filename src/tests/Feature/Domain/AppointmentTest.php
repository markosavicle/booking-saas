<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_cast_to_enum(): void
    {
        $appointment = Appointment::factory()->pending()->create();

        $this->assertSame(AppointmentStatus::Pending, $appointment->fresh()->status);
    }

    public function test_blocking_scope_excludes_canceled_appointments(): void
    {
        $service = Service::factory()->create();
        $confirmed = Appointment::factory()->forService($service)->create();
        $pending = Appointment::factory()->forService($service)->pending()->create();
        Appointment::factory()->forService($service)->canceled()->create();

        $ids = Appointment::blocking()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$confirmed->id, $pending->id], $ids);
    }

    /**
     * Existing appointment is 10:00–11:00.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function overlapCases(): array
    {
        return [
            'identical' => ['10:00', '11:00', true],
            'starts inside' => ['10:30', '11:30', true],
            'ends inside' => ['09:30', '10:30', true],
            'contains existing' => ['09:00', '12:00', true],
            'inside existing' => ['10:15', '10:45', true],
            'ends exactly at start' => ['09:00', '10:00', false],
            'starts exactly at end' => ['11:00', '12:00', false],
            'entirely before' => ['08:00', '09:00', false],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_overlapping_scope_uses_half_open_intervals(string $from, string $to, bool $expected): void
    {
        $day = CarbonImmutable::parse('2026-09-28');
        Appointment::factory()->create([
            'start_time' => $day->setTimeFromTimeString('10:00'),
            'end_time' => $day->setTimeFromTimeString('11:00'),
        ]);

        $overlaps = Appointment::overlapping(
            $day->setTimeFromTimeString($from),
            $day->setTimeFromTimeString($to),
        )->exists();

        $this->assertSame($expected, $overlaps);
    }
}
