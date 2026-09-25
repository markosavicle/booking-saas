<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_builds_a_consistent_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Tenant::count());
        $this->assertSame(1, User::where('role', UserRole::SuperAdmin)->count());

        Tenant::with(['users', 'services', 'businessHours', 'appointments'])->get()
            ->each(function (Tenant $tenant): void {
                $this->assertSame(
                    [UserRole::TenantAdmin],
                    $tenant->users->pluck('role')->unique()->values()->all(),
                );
                $this->assertCount(3, $tenant->services);
                $this->assertCount(6, $tenant->businessHours);
                $this->assertCount(2, $tenant->appointments);
            });
    }

    public function test_tenant_admin_emails_are_ascii(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'nis@example.com')->exists());
    }

    public function test_seeded_appointments_are_consistent_and_bookable(): void
    {
        $this->seed(DatabaseSeeder::class);

        Appointment::with(['user', 'service', 'tenant.businessHours'])->get()
            ->each(function (Appointment $appointment): void {
                $this->assertSame(UserRole::Customer, $appointment->user->role);
                $this->assertSame($appointment->tenant_id, $appointment->service->tenant_id);
                $this->assertNotNull(
                    $appointment->tenant->hoursFor($appointment->start_time),
                    'Seeded appointment falls on a closed day.',
                );
            });
    }
}
