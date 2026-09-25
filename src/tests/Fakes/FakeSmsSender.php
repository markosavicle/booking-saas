<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\SmsSender;

final class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }
}
