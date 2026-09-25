<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class AppointmentCanceled extends AppointmentNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $details = $this->details();

        return (new MailMessage)
            ->subject("Booking canceled - {$details->businessName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$details->serviceName} appointment with {$details->businessName} on {$details->when} has been canceled.")
            ->line('You are welcome to book a new time whenever suits you.');
    }

    public function toSms(object $notifiable): string
    {
        $details = $this->details();

        return "{$details->businessName}: your {$details->serviceName} on {$details->when} has been canceled.";
    }
}
