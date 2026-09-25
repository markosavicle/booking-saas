<?php

declare(strict_types=1);

namespace App\Sms;

use App\Contracts\SmsSender;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\RequestException;

final readonly class TwilioSmsSender implements SmsSender
{
    public function __construct(
        private HttpClient $http,
        private string $accountSid,
        private string $authToken,
        private string $from,
    ) {}

    /**
     * @throws RequestException So the queued notification is retried.
     */
    public function send(string $to, string $message): void
    {
        $this->http
            ->withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", [
                'To' => $to,
                'From' => $this->from,
                'Body' => $message,
            ])
            ->throw();
    }
}
