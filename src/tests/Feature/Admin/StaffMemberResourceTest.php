<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\StaffMemberResource;
use App\Filament\Resources\StaffMemberResource\Pages\CreateStaffMember;
use App\Filament\Resources\StaffMemberResource\Pages\EditStaffMember;
use App\Filament\Resources\StaffMemberResource\Pages\ListStaffMembers;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffMemberResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $shop;

    private Tenant $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Tenant::factory()->create(['name' => 'Own Shop']);
        $this->rival = Tenant::factory()->create(['name' => 'Rival Shop']);
    }

    public function test_a_shop_admin_only_sees_and_edits_their_own_staff(): void
    {
        $own = StaffMember::factory()->for($this->shop)->create();
        $foreign = StaffMember::factory()->for($this->rival)->create();

        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(ListStaffMembers::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign])
            ->assertTableColumnHidden('tenant.name');

        $this->get(StaffMemberResource::getUrl('edit', ['record' => $own]))->assertOk();
        $this->get(StaffMemberResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();
    }

    public function test_a_shop_admin_adds_a_barber_to_their_own_shop_without_picking_one(): void
    {
        $cut = Service::factory()->for($this->shop)->create(['name' => 'Cut']);
        Service::factory()->for($this->rival)->create(['name' => 'Rival Cut']);

        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(CreateStaffMember::class)
            ->assertFormFieldIsHidden('tenant_id')
            ->assertFormFieldExists('services', fn (Select $field): bool => $field->getOptions() === [$cut->id => 'Cut'])
            ->fillForm(['name' => 'Ana', 'services' => [$cut->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $ana = StaffMember::where('name', 'Ana')->sole();
        $this->assertSame($this->shop->id, $ana->tenant_id);
        $this->assertSame([$cut->id], $ana->services->modelKeys());
        $this->assertTrue($ana->is_active);
    }

    public function test_a_shop_admin_cannot_move_a_barber_to_another_shop(): void
    {
        $barber = StaffMember::factory()->for($this->shop)->create();

        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(EditStaffMember::class, ['record' => $barber->getRouteKey()])
            ->set('data.tenant_id', $this->rival->id)
            ->call('save');

        $this->assertSame($this->shop->id, $barber->refresh()->tenant_id);
    }

    public function test_a_barber_can_only_be_given_their_own_shops_services(): void
    {
        $own = Service::factory()->for($this->shop)->create();
        $foreign = Service::factory()->for($this->rival)->create();

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateStaffMember::class)
            ->fillForm(['tenant_id' => $this->shop->id, 'name' => 'Ana', 'services' => [$own->id, $foreign->id]])
            ->call('create')
            ->assertHasFormErrors(['services']);

        $this->assertDatabaseMissing('staff_members', ['name' => 'Ana']);
    }

    public function test_switching_shop_clears_the_picked_services(): void
    {
        $own = Service::factory()->for($this->shop)->create();
        $foreign = Service::factory()->for($this->rival)->create(['name' => 'Rival Cut']);

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateStaffMember::class)
            ->fillForm(['tenant_id' => $this->shop->id, 'services' => [$own->id]])
            ->fillForm(['tenant_id' => $this->rival->id])
            ->assertFormSet(['services' => []])
            ->assertFormFieldExists('services', fn (Select $field): bool => $field->getOptions() === [$foreign->id => 'Rival Cut']);
    }

    public function test_a_super_admin_manages_staff_across_shops(): void
    {
        $own = StaffMember::factory()->for($this->shop)->create();
        $foreign = StaffMember::factory()->for($this->rival)->inactive()->create();

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ListStaffMembers::class)
            ->assertCanSeeTableRecords([$own, $foreign])
            ->filterTable('tenant', $this->rival->id)
            ->assertCanSeeTableRecords([$foreign])
            ->assertCanNotSeeTableRecords([$own]);

        Livewire::test(ListStaffMembers::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$foreign])
            ->assertCanNotSeeTableRecords([$own]);
    }

    public function test_barbers_with_upcoming_customers_are_deactivated_not_deleted(): void
    {
        $service = Service::factory()->for($this->shop)->create();
        $busy = StaffMember::factory()->performing($service)->create();
        $idle = StaffMember::factory()->performing($service)->create();
        Appointment::factory()->forService($service)->create([
            'staff_member_id' => $busy->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
        ]);
        // Past visits are history, not a reason to keep someone who has left.
        Appointment::factory()->forService($service)->create([
            'staff_member_id' => $idle->id,
            'start_time' => now()->subWeek(),
            'end_time' => now()->subWeek()->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(ListStaffMembers::class)
            ->assertTableActionHidden(DeleteAction::class, $busy->getKey())
            ->assertTableActionVisible(DeleteAction::class, $idle->getKey())
            ->callTableAction(DeleteAction::class, $idle->getKey());

        $this->assertModelMissing($idle);
        $this->assertModelExists($busy);
    }
}
