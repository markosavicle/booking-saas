<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        Service::factory()->for($this->tenantA)->count(2)->create();
        Service::factory()->for($this->tenantB)->count(3)->create();
    }

    public function test_tenant_admin_only_sees_own_tenant_records(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

        $this->assertSame(2, Service::count());
        $this->assertTrue(Service::pluck('tenant_id')->every(fn (int $id): bool => $id === $this->tenantA->id));
    }

    public function test_customer_sees_services_across_all_tenants(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertSame(5, Service::count());
    }

    public function test_super_admin_sees_everything(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertSame(5, Service::count());
    }

    public function test_guest_queries_are_not_scoped(): void
    {
        $this->assertSame(5, Service::count());
    }

    public function test_tenant_admin_cannot_create_records_in_another_tenant(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->tenantA)->create());

        $service = Service::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Sneaky Cut',
            'duration_minutes' => 30,
            'price' => 10,
        ]);

        $this->assertSame($this->tenantA->id, $service->tenant_id);
    }

    public function test_customer_booking_keeps_the_service_tenant(): void
    {
        $customer = User::factory()->create();
        $service = Service::where('tenant_id', $this->tenantB->id)->firstOrFail();
        $this->actingAs($customer);

        $appointment = Appointment::factory()->forService($service)->create(['user_id' => $customer->id]);

        $this->assertSame($this->tenantB->id, $appointment->tenant_id);
    }
}
