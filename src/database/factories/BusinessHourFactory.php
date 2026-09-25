<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessHour;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessHour>
 */
class BusinessHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'day_of_week' => fake()->numberBetween(1, 6),
            'opens_at' => '09:00',
            'closes_at' => '17:00',
        ];
    }
}
