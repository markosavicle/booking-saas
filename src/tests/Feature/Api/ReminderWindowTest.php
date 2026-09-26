<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * "Now" is Monday 08:00; the shop opens 09:00. A booking made inside the reminder
 * window is already covered by its confirmation and must not get a reminder too.
 */
class ReminderWindowTest extends BookingTestCase
{
    private const string TUESDAY = '2026-09-29';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['booking.reminder_lead_hours' => 24]);
        $this->customer = User::factory()->create();
    }

    private function bookAt(string $startTime): TestResponse
    {
        return $this->actingAs($this->customer)->postJson('/api/bookings', [
            'service_id' => $this->haircut->id,
            'start_time' => $startTime,
        ])->assertCreated();
    }

    public function test_a_booking_inside_the_window_is_marked_as_reminded_and_never_reminded(): void
    {
        $this->bookAt(self::MONDAY.' 09:00'); // 1 hour away

        $this->assertTrue(Appointment::sole()->reminder_sent_at->equalTo(now()));

        $this->artisan('appointments:send-reminders')->expectsOutput('Queued 0 appointment reminder(s).');
        Notification::assertNotSentTo($this->customer, AppointmentReminder::class);
    }

    public function test_a_booking_beyond_the_window_is_reminded_once_it_enters_it(): void
    {
        $this->bookAt(self::TUESDAY.' 09:00'); // 25 hours away

        $this->assertNull(Appointment::sole()->reminder_sent_at);
        $this->artisan('appointments:send-reminders')->expectsOutput('Queued 0 appointment reminder(s).');

        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:00'));
        $this->artisan('appointments:send-reminders')->expectsOutput('Queued 1 appointment reminder(s).');

        Notification::assertSentToTimes($this->customer, AppointmentReminder::class, 1);
    }

    public function test_the_boundary_matches_the_reminder_command(): void
    {
        // Exactly lead_hours away: the command would include it (start <= now + lead), so it counts as covered.
        config(['booking.reminder_lead_hours' => 25]);

        $this->bookAt(self::TUESDAY.' 09:00');

        $this->assertNotNull(Appointment::sole()->reminder_sent_at);
    }

    public function test_the_window_follows_the_configured_lead_time(): void
    {
        config(['booking.reminder_lead_hours' => 2]);

        $this->bookAt(self::MONDAY.' 11:00'); // 3 hours away

        $this->assertNull(Appointment::sole()->reminder_sent_at);

        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:00'));
        $this->artisan('appointments:send-reminders')->expectsOutput('Queued 1 appointment reminder(s).');
    }
}
