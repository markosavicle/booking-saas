<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Contracts\SmsSender;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Contracts\SmsNotification;
use App\Sms\LogSmsSender;
use App\Sms\TwilioSmsSender;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class SmsDriversTest extends TestCase
{
    public function test_the_log_driver_is_bound_by_default(): void
    {
        config(['services.sms.driver' => 'log']);
        $this->app->forgetInstance(SmsSender::class);

        $this->assertInstanceOf(LogSmsSender::class, $this->app->make(SmsSender::class));
    }

    public function test_the_twilio_driver_is_bound_when_configured(): void
    {
        config(['services.sms.driver' => 'twilio', 'services.twilio' => ['sid' => 'AC1', 'token' => 't', 'from' => '+1000']]);
        $this->app->forgetInstance(SmsSender::class);

        $this->assertInstanceOf(TwilioSmsSender::class, $this->app->make(SmsSender::class));
    }

    public function test_an_unknown_driver_fails_loudly(): void
    {
        config(['services.sms.driver' => 'carrier-pigeon']);
        $this->app->forgetInstance(SmsSender::class);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(SmsSender::class);
    }

    public function test_the_log_driver_writes_the_message_to_the_log(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('SMS sent', ['to' => '+15551234567', 'message' => 'Hi']);

        (new LogSmsSender(Log::channel()))->send('+15551234567', 'Hi');
    }

    public function test_the_twilio_driver_posts_to_the_messages_api(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        $this->twilio()->send('+15551234567', 'Hello there');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC123:secret'))
            && $request['To'] === '+15551234567'
            && $request['From'] === '+15550000000'
            && $request['Body'] === 'Hello there');
    }

    public function test_the_twilio_driver_throws_on_failure_so_the_queue_retries(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'bad number'], 400)]);

        $this->expectException(RequestException::class);
        $this->twilio()->send('+1', 'Hello');
    }

    public function test_the_channel_sends_to_the_notifiables_sms_route(): void
    {
        $sender = new FakeSmsSender;

        (new SmsChannel($sender))->send(new User(['phone' => '+15551234567']), $this->smsNotification('Hi'));

        $this->assertSame([['to' => '+15551234567', 'message' => 'Hi']], $sender->sent);
    }

    public function test_the_channel_does_nothing_without_a_phone_number(): void
    {
        $sender = new FakeSmsSender;

        (new SmsChannel($sender))->send(new User(['phone' => null]), $this->smsNotification('Hi'));

        $this->assertSame([], $sender->sent);
    }

    private function twilio(): TwilioSmsSender
    {
        return new TwilioSmsSender($this->app->make(HttpClient::class), 'AC123', 'secret', '+15550000000');
    }

    private function smsNotification(string $text): Notification&SmsNotification
    {
        return new class($text) extends Notification implements SmsNotification
        {
            public function __construct(private readonly string $text) {}

            public function toSms(object $notifiable): string
            {
                return $this->text;
            }
        };
    }
}
