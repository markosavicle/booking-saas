<?php

namespace Tests\Feature;

use App\Jobs\SendBookingConfirmationJob;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_booking_dispatches_email_job(): void
    {
        Queue::fake();

        // 1. Create a user, tenant, and service
        $tenant = Tenant::factory()->create(['name' => 'Acme Spa']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $service = Service::factory()->create(['name' => 'Massage']);

        // 2. Mock your internal BookingService so we don't trigger real scheduling logic
        $this->mock(BookingService::class, function ($mock) {
            $appointment = new \App\Models\Appointment();
            $appointment->forceFill(['id' => 999]); // Bypasses mass assignment protection

            $mock->shouldReceive('createBooking')
                 ->once()
                 ->andReturn($appointment);
        });

        // 3. Authenticate and send the payload
        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'service_id' => $service->id,
            'start_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        // 4. Assert the response and that the email was queued
        // Using dump() will print the JSON response so we can read the exact error if it fails
        $response->dump()
                 ->assertStatus(201)
                 ->assertJsonFragment(['status' => 'email_queued']);

        Queue::assertPushed(SendBookingConfirmationJob::class);
    }

    public function test_invalid_booking_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        // Missing service_id and providing a past date
        $response = $this->actingAs($user)->postJson('/api/bookings', [
            'start_time' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['service_id', 'start_time']);

        Queue::assertNothingPushed();
    }
}
