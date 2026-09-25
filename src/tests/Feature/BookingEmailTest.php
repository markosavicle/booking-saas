<?php

namespace Tests\Feature;

use App\Jobs\SendBookingConfirmationJob;
use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_confirmation_job_can_be_queued(): void
    {
        Queue::fake();
        
        $appointment = Appointment::factory()->create();
        SendBookingConfirmationJob::dispatch($appointment);
        
        Queue::assertPushed(SendBookingConfirmationJob::class);
    }

    public function test_mailable_renders_correct_booking_details(): void
    {
        $appointment = Appointment::factory()->create();
        
        $mailable = new BookingConfirmationMail(
            $appointment->user->name,
            $appointment->tenant->name,
            $appointment->service->name,
            $appointment->start_time->format('Y-m-d H:i:s')
        );
        
        $mailable->assertSeeInHtml($appointment->user->name);
        $mailable->assertSeeInHtml($appointment->service->name);
    }
}
