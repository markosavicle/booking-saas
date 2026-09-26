<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class AppointmentConfirmed extends AppointmentNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $details = $this->details();

        return (new MailMessage)
            ->subject("Booking confirmed - {$details->businessName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your appointment with {$details->businessName} is confirmed.")
            ->line("Service: {$details->serviceName}")
            ->lineIf($details->staffName !== null, "With: {$details->staffName}")
            ->line("When: {$details->when}")
            ->line('If you can no longer make it, please cancel in advance so someone else can take the slot.')
            ->action('Cancel booking', $this->appointment->cancelUrl());
    }

    public function toSms(object $notifiable): string
    {
        $details = $this->details();

        return "{$details->businessName}: your {$details->serviceName} is confirmed for {$details->when}. Cancel: {$this->appointment->cancelUrl()}";
    }
}
