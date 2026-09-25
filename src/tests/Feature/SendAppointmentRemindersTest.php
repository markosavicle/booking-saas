<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SendAppointmentReminders;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class SendAppointmentRemindersTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->now = CarbonImmutable::parse('2026-09-30 10:00');
        $this->travelTo($this->now);
        $this->service = Service::factory()->create();
    }

    private function appointmentIn(string $offset, ?string $state = null): Appointment
    {
        $factory = Appointment::factory()->forService($this->service)->at($this->now->modify($offset));

        return ($state === null ? $factory : $factory->{$state}())->create();
    }

    private function runReminders(array $options = []): PendingCommand
    {
        return $this->artisan('appointments:send-reminders', $options);
    }

    public function test_it_reminds_confirmed_appointments_starting_within_the_next_24_hours(): void
    {
        $soon = $this->appointmentIn('+2 hours');
        $edge = $this->appointmentIn('+24 hours');
        $tooFar = $this->appointmentIn('+24 hours +1 minute');

        $this->runReminders()->expectsOutput('Queued 2 appointment reminder(s).')->assertSuccessful();

        Notification::assertSentToTimes($soon->user, AppointmentReminder::class, 1);
        Notification::assertSentTo($edge->user, fn (AppointmentReminder $n): bool => $n->appointment->is($edge));
        Notification::assertNothingSentTo($tooFar->user);
        $this->assertNull($tooFar->fresh()->reminder_sent_at);
    }

    public function test_it_skips_canceled_pending_past_and_already_reminded_appointments(): void
    {
        $canceled = $this->appointmentIn('+2 hours', 'canceled');
        $pending = $this->appointmentIn('+2 hours', 'pending');
        $past = $this->appointmentIn('-1 hour');
        $startingNow = $this->appointmentIn('+0 minutes');
        $reminded = $this->appointmentIn('+2 hours');
        $reminded->forceFill(['reminder_sent_at' => $this->now->subHour()])->save();

        $this->runReminders()->expectsOutput('Queued 0 appointment reminder(s).')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($canceled->fresh()->reminder_sent_at);
        $this->assertNull($pending->fresh()->reminder_sent_at);
        $this->assertNull($past->fresh()->reminder_sent_at);
        $this->assertNull($startingNow->fresh()->reminder_sent_at);
    }

    public function test_it_records_when_the_reminder_was_sent(): void
    {
        $appointment = $this->appointmentIn('+3 hours');

        $this->runReminders()->assertSuccessful();

        $this->assertTrue($appointment->fresh()->reminder_sent_at->equalTo($this->now));
    }

    public function test_running_repeatedly_never_sends_a_reminder_twice(): void
    {
        $appointment = $this->appointmentIn('+23 hours');

        $this->runReminders()->expectsOutput('Queued 1 appointment reminder(s).');
        $this->travel(5)->minutes();
        $this->runReminders()->expectsOutput('Queued 0 appointment reminder(s).');
        $this->travel(5)->hours();
        $this->runReminders()->expectsOutput('Queued 0 appointment reminder(s).');

        Notification::assertSentToTimes($appointment->user, AppointmentReminder::class, 1);
    }

    public function test_an_appointment_claimed_by_a_concurrent_run_is_not_sent_again(): void
    {
        $appointment = $this->appointmentIn('+2 hours');

        // Simulate another worker claiming the row between our SELECT and our UPDATE.
        $raced = false;
        DB::listen(function (QueryExecuted $query) use (&$raced): void {
            if (! $raced && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "appointments"')) {
                $raced = true;
                DB::table('appointments')->update(['reminder_sent_at' => now()]);
            }
        });

        $this->runReminders()->expectsOutput('Queued 0 appointment reminder(s).')->assertSuccessful();

        $this->assertTrue($raced);
        Notification::assertNothingSentTo($appointment->user);
    }

    public function test_a_missed_scheduler_run_is_caught_up_rather_than_dropped(): void
    {
        // Scheduler was down: this appointment passed the 24h mark hours ago.
        $appointment = $this->appointmentIn('+5 hours');

        $this->runReminders()->assertSuccessful();

        Notification::assertSentTo($appointment->user, AppointmentReminder::class);
    }

    public function test_the_window_can_be_changed_with_the_hours_option(): void
    {
        $inTwoHours = $this->appointmentIn('+2 hours');
        $inFiveHours = $this->appointmentIn('+5 hours');

        $this->runReminders(['--hours' => 3])->expectsOutput('Queued 1 appointment reminder(s).');

        Notification::assertSentTo($inTwoHours->user, AppointmentReminder::class);
        Notification::assertNothingSentTo($inFiveHours->user);
    }

    public function test_a_non_positive_hours_option_is_rejected(): void
    {
        $this->appointmentIn('+2 hours');

        $this->runReminders(['--hours' => 0])
            ->expectsOutput('--hours must be a positive integer.')
            ->assertExitCode(SendAppointmentReminders::INVALID);

        Notification::assertNothingSent();
    }

    public function test_the_tenant_scope_does_not_hide_appointments_from_the_command(): void
    {
        $appointment = $this->appointmentIn('+2 hours');
        $this->actingAs(User::factory()->tenantAdmin()->create());

        $this->runReminders()->expectsOutput('Queued 1 appointment reminder(s).');

        Notification::assertSentTo($appointment->user, AppointmentReminder::class);
    }

    public function test_customers_are_eager_loaded(): void
    {
        $this->appointmentIn('+1 hour');
        $this->appointmentIn('+2 hours');
        $this->appointmentIn('+3 hours');

        DB::enableQueryLog();
        $this->runReminders()->expectsOutput('Queued 3 appointment reminder(s).');

        $userQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from "users"'));
        $this->assertCount(1, $userQueries);
    }

    public function test_it_is_scheduled_without_overlapping(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'appointments:send-reminders'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
