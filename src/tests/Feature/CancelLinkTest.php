<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentCanceled;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Api\BookingTestCase;

class CancelLinkTest extends BookingTestCase
{
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant->update(['timezone' => 'Europe/Belgrade']);
        $this->appointment = $this->book($this->anna, '09:00'); // 11:00 in Belgrade
    }

    private function cancel(?string $token = null): TestResponse
    {
        return $this->post('/cancel/'.($token ?? $this->appointment->cancel_token));
    }

    public function test_every_appointment_gets_its_own_secret_token(): void
    {
        $other = $this->book($this->ben, '09:00');

        $this->assertSame(48, strlen($this->appointment->cancel_token));
        $this->assertNotSame($this->appointment->cancel_token, $other->cancel_token);
        $this->assertArrayNotHasKey('cancel_token', $this->appointment->toArray());
        $this->assertSame(url('/cancel/'.$this->appointment->cancel_token), $this->appointment->cancelUrl());
    }

    public function test_opening_the_link_shows_the_booking_without_canceling_it(): void
    {
        // Link scanners in mail and SMS apps fetch URLs automatically; GET must be safe.
        $this->get($this->appointment->cancelUrl())
            ->assertOk()
            ->assertSee('Haircut')
            ->assertSee('11:00')
            ->assertSee('Anna')
            ->assertSee('Cancel this booking')
            ->assertSee('noindex', false);

        $this->assertSame(AppointmentStatus::Confirmed, $this->appointment->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_one_click_cancels_the_booking_and_frees_the_slot(): void
    {
        $this->cancel()->assertRedirect($this->appointment->cancelUrl());

        $this->assertSame(AppointmentStatus::Canceled, $this->appointment->fresh()->status);
        Notification::assertSentToTimes($this->appointment->user, AppointmentCanceled::class, 1);

        $this->get($this->appointment->cancelUrl())
            ->assertSee('Booking <em class="gold-text italic">canceled</em>', false)
            ->assertDontSee('Cancel this booking');

        $this->getJson("/api/tenants/{$this->tenant->slug}/services/{$this->haircut->id}/availability?date=".self::MONDAY)
            ->assertJsonPath('data.0.staff_member_ids', [$this->anna->id, $this->ben->id]);
    }

    public function test_canceling_twice_is_harmless(): void
    {
        $this->cancel();
        $this->followingRedirects()->cancel()->assertOk()->assertSee('already been canceled');

        Notification::assertSentToTimes($this->appointment->user, AppointmentCanceled::class, 1);
    }

    public function test_appointments_that_have_started_cannot_be_canceled(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:05'));

        $this->get($this->appointment->cancelUrl())->assertOk()->assertDontSee('Cancel this booking')->assertSee('already started');
        $this->followingRedirects()->cancel()->assertSee('already started');

        $this->assertSame(AppointmentStatus::Confirmed, $this->appointment->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_unknown_tokens_are_not_found(): void
    {
        $this->get('/cancel/'.str_repeat('x', 48))->assertNotFound();
        $this->cancel(str_repeat('x', 48))->assertNotFound();

        $this->assertSame(AppointmentStatus::Confirmed, $this->appointment->fresh()->status);
    }

    public function test_the_cancel_link_is_rate_limited(): void
    {
        foreach (range(1, 20) as $i) {
            $this->get('/cancel/guess-'.$i)->assertNotFound();
        }

        $this->get($this->appointment->cancelUrl())->assertTooManyRequests();
    }
}
