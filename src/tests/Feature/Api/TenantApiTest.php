<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StaffMember;

class TenantApiTest extends BookingTestCase
{
    public function test_it_shows_the_public_tenant_profile(): void
    {
        Service::factory()->for($this->tenant)->inactive()->create();
        StaffMember::factory()->for($this->tenant)->inactive()->create();

        $this->getJson("/api/tenants/{$this->tenant->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $this->tenant->slug)
            ->assertJsonPath('data.business_hours.0', ['day_of_week' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'])
            ->assertJsonCount(6, 'data.business_hours')
            ->assertJsonPath('data.services.*.name', ['Haircut', 'VIP'])
            ->assertJsonPath('data.services.0.staff_member_ids', [$this->anna->id, $this->ben->id])
            ->assertJsonPath('data.services.1.staff_member_ids', [$this->anna->id])
            ->assertJsonPath('data.staff_members.*.name', ['Anna', 'Ben']);
    }

    public function test_unknown_slugs_return_404(): void
    {
        $this->getJson('/api/tenants/nope')->assertNotFound();
        $this->getJson("/api/tenants/{$this->tenant->id}")->assertNotFound();
    }
}
