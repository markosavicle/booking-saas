<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Sms\LogSmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\BookingTestCase;

class DemoModeTest extends BookingTestCase
{
    private function requestCode(): TestResponse
    {
        return $this->postJson('/api/booking-requests', [
            'service_id' => $this->haircut->id,
            'start_time' => self::MONDAY.' 09:00',
            'name' => 'Recruiter',
            'phone' => '+381601234567',
        ]);
    }

    private function enableDemoMode(): void
    {
        config(['booking.demo_mode' => true, 'services.sms.driver' => 'twilio']);
        // The SMS sender is a singleton; rebuild it under the new config.
        $this->app->forgetInstance(SmsSender::class);
    }

    public function test_live_mode_never_reveals_the_code_or_labels_the_site_as_a_demo(): void
    {
        $this->requestCode()->assertAccepted()->assertJsonMissingPath('data.demo_code');

        $this->get("/book/{$this->tenant->slug}")->assertOk()->assertDontSee('aria-label="Demo mode"', false);
        $this->get('/admin/login')->assertOk()->assertDontSee('DEMO MODE');
    }

    public function test_a_demo_visitor_books_with_the_code_shown_on_screen_and_nobody_is_texted(): void
    {
        Http::fake();
        $this->enableDemoMode();

        $this->assertInstanceOf(LogSmsSender::class, $this->app->make(SmsSender::class));

        $response = $this->requestCode()->assertAccepted();
        $code = $response->json('data.demo_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Everything else is the real flow: a wrong code still counts, the right one books.
        $this->postJson("/api/booking-requests/{$response->json('data.id')}/confirm", ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertUnprocessable();
        $this->postJson("/api/booking-requests/{$response->json('data.id')}/confirm", ['code' => $code])->assertCreated();

        $this->assertSame(AppointmentStatus::Confirmed, Appointment::sole()->status);
        Http::assertNothingSent();
    }

    public function test_every_page_says_it_is_a_demo(): void
    {
        $this->enableDemoMode();
        $appointment = $this->book($this->anna, '10:00');

        $this->get('/book')->assertOk()->assertSee('aria-label="Demo mode"', false)->assertSee('no SMS is sent');
        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('aria-label="Demo mode"', false)
            ->assertSee('Demo mode: nothing was texted.');
        $this->get($appointment->cancelUrl())->assertOk()->assertSee('aria-label="Demo mode"', false);
        $this->get('/admin/login')->assertOk()->assertSee('DEMO MODE');
    }
}
