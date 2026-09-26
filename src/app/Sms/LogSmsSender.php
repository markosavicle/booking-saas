<?php

declare(strict_types=1);

namespace App\Sms;

use App\Contracts\SmsSender;
use Psr\Log\LoggerInterface;

/**
 * Default driver for local/dev environments: writes messages to the log instead of sending.
 */
final readonly class LogSmsSender implements SmsSender
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function send(string $to, string $message): void
    {
        $this->logger->info('SMS sent', ['to' => $to, 'message' => $message]);
    }
}
