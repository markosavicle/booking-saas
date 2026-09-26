<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use Illuminate\Notifications\Messages\MailMessage;

final class AppointmentReminder extends AppointmentNotification
{
    /**
     * Re-checked when the queued job runs: the appointment may have been canceled
     * (or already started) between the command claiming it and the worker sending.
     * The queue re-fetches the appointment when the job is unserialized, so this is current.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->appointment->status === AppointmentStatus::Confirmed
            && $this->appointment->start_time->isFuture();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $details = $this->details();

        return (new MailMessage)
            ->subject("Reminder: your appointment at {$details->businessName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder of your upcoming appointment with {$details->businessName}.")
            ->line("Service: {$details->serviceName}")
            ->lineIf($details->staffName !== null, "With: {$details->staffName}")
            ->line("When: {$details->when}")
            ->line('If you can no longer make it, please cancel so someone else can take the slot.')
            ->action('Cancel booking', $this->appointment->cancelUrl());
    }

    public function toSms(object $notifiable): string
    {
        $details = $this->details();

        return "Reminder from {$details->businessName}: {$details->serviceName} on {$details->when}. Can't make it? {$this->appointment->cancelUrl()}";
    }
}
