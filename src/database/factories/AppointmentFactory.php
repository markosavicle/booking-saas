<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startTime = now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(9, 16), 0);
        $duration = fake()->randomElement([30, 60]);

        return [
            'tenant_id' => Tenant::factory(),
            'service_id' => Service::factory(),
            'user_id' => User::factory(),
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes($duration),
            'status' => AppointmentStatus::Confirmed,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => AppointmentStatus::Pending]);
    }

    public function canceled(): static
    {
        return $this->state(['status' => AppointmentStatus::Canceled]);
    }

    /**
     * Book a service of the given tenant, keeping tenant_id and service_id consistent.
     */
    public function forService(Service $service): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $service->tenant_id,
            'service_id' => $service->id,
        ]);
    }
}
