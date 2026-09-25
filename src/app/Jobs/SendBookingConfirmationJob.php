<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use App\Models\Appointment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Appointment $appointment
    ) {}

    public function handle(): void
    {
        // Ensure the relationships are loaded before sending
        $this->appointment->loadMissing(['user', 'tenant', 'service']);

        Mail::to($this->appointment->user->email)->send(
            new BookingConfirmationMail(
                $this->appointment->user->name,
                $this->appointment->tenant->name ?? 'Our Business',
                $this->appointment->service->name,
                $this->appointment->start_time->format('Y-m-d H:i:s')
            )
        );
    }
}
