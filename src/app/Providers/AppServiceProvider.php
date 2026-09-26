<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Sms\LogSmsSender;
use App\Sms\TwilioSmsSender;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    /**
     * Keys must differ between limits of one limiter, or their counters collide.
     */
    private function configureRateLimiting(): void
    {
        // Every hit sends a paid SMS: cap bursts and totals per sender IP and per target phone.
        RateLimiter::for('booking-otp', function (Request $request): array {
            $phone = PhoneNumber::normalize($request->input('phone'));

            return [
                Limit::perMinute(3)->by('ip:'.$request->ip()),
                Limit::perMinute(3)->by('phone:'.$phone),
                Limit::perHour(5)->by('phone-hour:'.$phone),
                Limit::perDay(20)->by('ip-day:'.$request->ip()),
            ];
        });

        // Guesses per code are capped by the broker; this caps guessing across codes.
        RateLimiter::for('booking-confirm', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('bookings', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id ?: $request->ip()));

        RateLimiter::for('booking-cancel', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
    }
}
