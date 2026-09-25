<?php

namespace Tests\Feature;

use App\Jobs\SendBookingConfirmationJob;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendAppointmentRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_sent_for_appointments_24_hours_away(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['tenant_id' => $tenant->id]); // Aligning the tenant!

        // Create an appointment exactly 24 hours from now
        Appointment::factory()->create([
            'user_id'    => $user->id,
            'tenant_id'  => $tenant->id,
            'service_id' => $service->id,
            'start_time' => now()->addDays(1)->startOfMinute(),
        ]);

        // Create an appointment 48 hours from now (should be ignored)
        Appointment::factory()->create([
            'user_id'    => $user->id,
            'tenant_id'  => $tenant->id,
            'service_id' => $service->id,
            'start_time' => now()->addDays(2)->startOfMinute(),
        ]);

        // Execute the Artisan console command
        $this->artisan('appointments:send-reminders')
             ->expectsOutputToContain('Found 1 appointments requiring reminders.')
             ->assertExitCode(0);

        // Assert that the job was pushed exactly once for the correct appointment
        Queue::assertPushed(SendBookingConfirmationJob::class, 1);
    }
}
