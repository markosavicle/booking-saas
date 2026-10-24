<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Models\Appointment;
use App\Models\Scopes\TenantScope;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppointmentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_admin_cannot_book_a_rival_shops_service_or_staff(): void
    {
        $shop = Tenant::factory()->create();
        $rival = Tenant::factory()->create();
        $rivalService = Service::factory()->for($rival)->create();
        $rivalStaff = StaffMember::factory()->for($rival)->performing($rivalService)->create();
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->tenantAdmin($shop)->create());

        Livewire::test(CreateAppointment::class)
            ->fillForm([
                'tenant_id' => $shop->id,
                'service_id' => $rivalService->id,
                'staff_member_id' => $rivalStaff->id,
                'user_id' => $customer->id,
                'start_time' => now()->addDay()->setTime(10, 0),
                'end_time' => now()->addDay()->setTime(10, 30),
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['service_id', 'staff_member_id']);

        $this->assertFalse(Appointment::withoutGlobalScope(TenantScope::class)->where('staff_member_id', $rivalStaff->id)->exists());
    }

    public function test_a_shop_admin_books_their_own_shops_service_for_a_customer(): void
    {
        $shop = Tenant::factory()->create();
        $service = Service::factory()->for($shop)->create();
        $staff = StaffMember::factory()->for($shop)->performing($service)->create();
        $customer = User::factory()->create();

        $this->actingAs(User::factory()->tenantAdmin($shop)->create());

        Livewire::test(CreateAppointment::class)
            ->fillForm([
                'service_id' => $service->id,
                'staff_member_id' => $staff->id,
                'user_id' => $customer->id,
                'start_time' => now()->addDay()->setTime(10, 0),
                'end_time' => now()->addDay()->setTime(10, 30),
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('appointments', ['tenant_id' => $shop->id, 'staff_member_id' => $staff->id, 'user_id' => $customer->id]);
    }

    public function test_a_shop_admin_cannot_pick_staff_accounts_as_the_customer(): void
    {
        $shop = Tenant::factory()->create();
        $service = Service::factory()->for($shop)->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs(User::factory()->tenantAdmin($shop)->create());

        Livewire::test(CreateAppointment::class)
            ->fillForm([
                'service_id' => $service->id,
                'user_id' => $superAdmin->id,
                'start_time' => now()->addDay()->setTime(10, 0),
                'end_time' => now()->addDay()->setTime(10, 30),
                'status' => AppointmentStatus::Confirmed->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['user_id']);
    }
}
