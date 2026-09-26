<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSmsSender;

class BookingRateLimitTest extends BookingTestCase
{
    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    private function requestCode(string $phone, string $ip = '203.0.113.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/booking-requests', [
            'service_id' => $this->haircut->id,
            'start_time' => self::MONDAY.' 09:00',
            'name' => 'Guest',
            'phone' => $phone,
        ]);
    }

    public function test_one_ip_can_request_three_codes_per_minute(): void
    {
        foreach (['+381600000001', '+381600000002', '+381600000003'] as $phone) {
            $this->requestCode($phone)->assertAccepted();
        }

        $this->requestCode('+381600000004')->assertTooManyRequests()->assertHeader('Retry-After');
        $this->requestCode('+381600000004', '203.0.113.2')->assertAccepted();
        $this->assertCount(4, $this->sms->sent);
    }

    public function test_one_phone_can_receive_three_codes_per_minute_whatever_the_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->requestCode('+381601111111', "198.51.100.{$i}")->assertAccepted();
        }

        // Same number, differently formatted, from yet another IP: still the same phone.
        $this->requestCode('00381 60 111 1111', '198.51.100.9')->assertTooManyRequests();
        $this->assertCount(3, $this->sms->sent);
    }

    public function test_limits_reset_after_a_minute(): void
    {
        foreach (range(1, 3) as $i) {
            $this->requestCode('+38160000000'.$i);
        }
        $this->requestCode('+381600000004')->assertTooManyRequests();

        $this->travel(61)->seconds();

        $this->requestCode('+381600000004')->assertAccepted();
    }

    public function test_one_phone_can_receive_at_most_five_codes_per_hour(): void
    {
        $sent = 0;
        foreach (range(1, 5) as $i) {
            $this->requestCode('+381602222222', "192.0.2.{$i}")->assertAccepted();
            $sent++;
            $this->travel(61)->seconds();
        }

        $this->requestCode('+381602222222', '192.0.2.99')->assertTooManyRequests();
        $this->assertCount($sent, $this->sms->sent);
    }

    public function test_one_ip_can_request_at_most_twenty_codes_per_day(): void
    {
        foreach (range(1, 20) as $i) {
            $this->requestCode(sprintf('+3816%08d', $i))->assertAccepted();
            $this->travel(21)->seconds();
        }

        $this->requestCode('+381699999999')->assertTooManyRequests();
    }

    public function test_code_confirmation_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/booking-requests/'.Str::uuid().'/confirm', ['code' => '123456'])->assertUnprocessable();
        }

        $this->postJson('/api/booking-requests/'.Str::uuid().'/confirm', ['code' => '123456'])->assertTooManyRequests();
    }

    public function test_authenticated_booking_creation_is_rate_limited_per_user(): void
    {
        $customer = User::factory()->create();

        foreach (range(1, 10) as $i) {
            $this->actingAs($customer)->postJson('/api/bookings', [])->assertUnprocessable();
        }

        $this->actingAs($customer)->postJson('/api/bookings', [])->assertTooManyRequests();
        $this->actingAs(User::factory()->create())->postJson('/api/bookings', [])->assertUnprocessable();
    }
}
