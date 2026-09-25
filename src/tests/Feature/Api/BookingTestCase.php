<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Shared calendar: a shop open Mon–Sat 09:00–12:00, "now" is Monday 08:00.
 * Anna performs both services; Ben only does the 30-minute haircut.
 */
abstract class BookingTestCase extends TestCase
{
    use RefreshDatabase;

    protected const string MONDAY = '2026-09-28';

    protected const string SUNDAY = '2026-09-27';

    protected Tenant $tenant;

    protected Service $haircut;

    protected Service $vip;

    protected StaffMember $anna;

    protected StaffMember $ben;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config(['booking.slot_interval_minutes' => 30, 'booking.max_advance_days' => 60]);
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 08:00'));

        $this->tenant = Tenant::factory()->withStandardHours('09:00', '12:00')->create();
        $this->haircut = Service::factory()->for($this->tenant)->create(['name' => 'Haircut', 'duration_minutes' => 30]);
        $this->vip = Service::factory()->for($this->tenant)->create(['name' => 'VIP', 'duration_minutes' => 60]);
        $this->anna = StaffMember::factory()->performing($this->haircut, $this->vip)->create(['name' => 'Anna']);
        $this->ben = StaffMember::factory()->performing($this->haircut)->create(['name' => 'Ben']);
    }

    protected function book(StaffMember $staff, string $time, int $minutes = 30): Appointment
    {
        return Appointment::factory()
            ->forService($this->haircut)
            ->forStaff($staff)
            ->at(CarbonImmutable::parse(self::MONDAY.' '.$time), $minutes)
            ->create();
    }
}
