<?php

namespace App\Jobs;

use App\Mail\BookingConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $recipientEmail,
        public string $customerName,
        public string $tenantName,
        public string $serviceName,
        public string $appointmentTime
    ) {}

    public function handle(): void
    {
        Mail::to($this->recipientEmail)->send(
            new BookingConfirmationMail(
                $this->customerName,
                $this->tenantName,
                $this->serviceName,
                $this->appointmentTime
            )
        );
    }
}
