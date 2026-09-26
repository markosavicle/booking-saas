<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\SmsSender;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Notifications\Notification;

final readonly class SmsChannel
{
    public function __construct(
        private SmsSender $sender,
    ) {}

    public function send(object $notifiable, Notification&SmsNotification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (blank($to)) {
            return;
        }

        $this->sender->send($to, $notification->toSms($notifiable));
    }
}
