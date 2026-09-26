<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Sms\LogSmsSender;
use App\Sms\TwilioSmsSender;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsSender::class, fn (Application $app): SmsSender => match (config('services.sms.driver')) {
            'twilio' => new TwilioSmsSender(
                $app->make(HttpClient::class),
                (string) config('services.twilio.sid'),
                (string) config('services.twilio.token'),
                (string) config('services.twilio.from'),
            ),
            'log' => new LogSmsSender($app->make('log')->channel(config('services.sms.log_channel'))),
            default => throw new \InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
