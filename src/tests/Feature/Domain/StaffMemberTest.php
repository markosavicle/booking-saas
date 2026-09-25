<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualified_staff_excludes_inactive_and_unassigned_members(): void
    {
        $service = Service::factory()->create();
        $qualified = StaffMember::factory()->performing($service)->create();
        StaffMember::factory()->performing($service)->inactive()->create();
        StaffMember::factory()->for($service->tenant)->create();

        $this->assertSame([$qualified->id], $service->qualifiedStaff()->pluck('staff_members.id')->all());
    }

    public function test_tenant_admins_only_see_their_own_staff(): void
    {
        $mine = StaffMember::factory()->create();
        StaffMember::factory()->create();

        $this->actingAs(User::factory()->tenantAdmin($mine->tenant)->create());

        $this->assertSame([$mine->id], StaffMember::pluck('id')->all());
    }

    public function test_admins_can_open_the_staff_panel_page(): void
    {
        StaffMember::factory()->performing(Service::factory()->create())->create(['name' => 'Anna']);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/staff-members')
            ->assertOk()
            ->assertSee('Anna');
    }
}
