<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $tenantName,
        public string $serviceName,
        public string $appointmentTime
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Booking Confirmation - ' . $this->tenantName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
