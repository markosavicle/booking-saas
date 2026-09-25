<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;

class TenantApiTest extends BookingTestCase
{
    public function test_it_shows_the_public_tenant_profile(): void
    {
        Service::factory()->for($this->tenant)->inactive()->create();
        StaffMember::factory()->for($this->tenant)->inactive()->create();

        $this->getJson("/api/tenants/{$this->tenant->slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $this->tenant->slug)
            ->assertJsonPath('data.timezone', 'UTC')
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

    public function test_it_lists_shops_alphabetically_for_the_booking_widget(): void
    {
        Tenant::factory()->create(['name' => 'Aardvark Barbers', 'timezone' => 'Europe/Belgrade']);

        $this->getJson('/api/tenants')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0', [
                'id' => Tenant::firstWhere('name', 'Aardvark Barbers')->id,
                'name' => 'Aardvark Barbers',
                'slug' => 'aardvark-barbers',
                'timezone' => 'Europe/Belgrade',
            ]);
    }

    public function test_the_booking_page_renders_and_deep_links_to_a_shop(): void
    {
        $this->withoutVite();

        $this->get('/book')->assertOk()->assertSee('bookingWidget(null)', false);
        $this->get("/book/{$this->tenant->slug}")->assertOk()->assertSee("bookingWidget('{$this->tenant->slug}')", false);
        $this->get('/book/unknown-shop')->assertNotFound();
    }
}
