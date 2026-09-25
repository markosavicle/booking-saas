<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Customer-facing appointment notification: always e-mail, plus SMS when the customer has a phone.
 */
abstract class AppointmentNotification extends Notification implements ShouldQueue, SmsNotification
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /**
     * The appointment may be deleted before the queue picks this up; drop the job silently.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Appointment $appointment,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return filled($notifiable->routeNotificationFor('sms', $this))
            ? ['mail', SmsChannel::class]
            : ['mail'];
    }

    protected function details(): AppointmentDetails
    {
        return AppointmentDetails::from($this->appointment);
    }
}
