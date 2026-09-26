<?php

declare(strict_types=1);

namespace App\Contracts;

interface SmsSender
{
    /**
     * @param  string  $to  E.164 phone number.
     */
    public function send(string $to, string $message): void;
}
