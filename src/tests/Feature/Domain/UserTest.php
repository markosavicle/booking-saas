<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_tenant_are_not_mass_assignable(): void
    {
        $tenant = Tenant::factory()->create();

        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'role' => UserRole::SuperAdmin->value,
            'tenant_id' => $tenant->id,
        ])->fresh();

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNull($user->tenant_id);
    }

    public function test_only_admins_can_access_the_filament_panel(): void
    {
        $panel = Filament::getDefaultPanel();

        $this->assertTrue(User::factory()->superAdmin()->create()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->tenantAdmin()->create()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->create()->canAccessPanel($panel));
    }

    public function test_customer_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }
}
