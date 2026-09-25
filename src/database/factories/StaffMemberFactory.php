<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->firstName(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Staff member of the service's tenant who is qualified to perform it.
     */
    public function performing(Service ...$services): static
    {
        return $this->state(['tenant_id' => $services[0]->tenant_id])
            ->afterCreating(fn (StaffMember $staff) => $staff->services()->attach(
                array_map(fn (Service $service): int => $service->id, $services),
            ));
    }
}
