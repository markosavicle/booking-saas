<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

interface SmsNotification
{
    public function toSms(object $notifiable): string;
}
