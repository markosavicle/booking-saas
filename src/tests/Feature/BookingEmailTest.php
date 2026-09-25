<?php

namespace Tests\Feature;

use App\Jobs\SendBookingConfirmationJob;
use App\Mail\BookingConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingEmailTest extends TestCase
{
    public function test_booking_confirmation_job_can_be_queued(): void
    {
        Queue::fake();

        SendBookingConfirmationJob::dispatch(
            'customer@example.com',
            'Alex Smith',
            'Acme Barbershop',
            'Haircut & Beard Trim',
            '2026-10-01 10:00 AM'
        );

        Queue::assertPushed(SendBookingConfirmationJob::class);
    }

    public function test_mailable_renders_correct_booking_details(): void
    {
        Mail::fake();

        $mailable = new BookingConfirmationMail(
            'Alex Smith',
            'Acme Barbershop',
            'Haircut & Beard Trim',
            '2026-10-01 10:00 AM'
        );

        $mailable->assertSeeInHtml('Alex Smith');
        $mailable->assertSeeInHtml('Acme Barbershop');
        $mailable->assertSeeInHtml('Haircut & Beard Trim');
    }
}
