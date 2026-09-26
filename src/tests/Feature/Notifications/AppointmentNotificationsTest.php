<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Contracts\SmsSender;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentCanceled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentNotification;
use App\Notifications\AppointmentReminder;
use App\Notifications\Channels\SmsChannel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mime\Email;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

class AppointmentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Appointment $appointment;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-30 10:00'));

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $tenant = Tenant::factory()->create(['name' => 'Acme Salon', 'timezone' => 'Europe/Belgrade']);
        $service = Service::factory()->for($tenant)->create(['name' => 'Haircut']);
        $staff = StaffMember::factory()->for($tenant)->create(['name' => 'Anna']);
        $this->customer = User::factory()->create(['name' => 'Jane Doe', 'phone' => '+15551234567']);

        // Stored as UTC; customers must see the shop's wall-clock time (UTC+2 in October).
        $this->appointment = Appointment::factory()
            ->forService($service)
            ->forStaff($staff)
            ->at(CarbonImmutable::parse('2026-10-01 12:30'))
            ->create(['user_id' => $this->customer->id, 'cancel_token' => 'the-cancel-token']);
    }

    /**
     * @return array<string, array{class-string<AppointmentNotification>, string, list<string>, string}>
     */
    public static function notifications(): array
    {
        return [
            'confirmation' => [
                AppointmentConfirmed::class,
                'Booking confirmed - Acme Salon',
                ['Hello Jane Doe', 'is confirmed', 'Service: Haircut', 'With: Anna', 'When: Thursday, October 1, 2026 at 14:30 CEST', 'Cancel booking', '/cancel/the-cancel-token'],
                'Acme Salon: your Haircut is confirmed for Thursday, October 1, 2026 at 14:30 CEST. Cancel: {cancel_url}',
            ],
            'cancellation' => [
                AppointmentCanceled::class,
                'Booking canceled - Acme Salon',
                ['Hello Jane Doe', 'Your Haircut appointment with Acme Salon on Thursday, October 1, 2026 at 14:30 CEST has been canceled.'],
                'Acme Salon: your Haircut on Thursday, October 1, 2026 at 14:30 CEST has been canceled.',
            ],
            'reminder' => [
                AppointmentReminder::class,
                'Reminder: your appointment at Acme Salon',
                ['Hello Jane Doe', 'reminder of your upcoming appointment', 'Service: Haircut', 'With: Anna', 'When: Thursday, October 1, 2026 at 14:30 CEST', 'Cancel booking', '/cancel/the-cancel-token'],
                "Reminder from Acme Salon: Haircut on Thursday, October 1, 2026 at 14:30 CEST. Can't make it? {cancel_url}",
            ],
        ];
    }

    /**
     * @param  class-string<AppointmentNotification>  $class
     * @param  list<string>  $expectedLines
     */
    #[DataProvider('notifications')]
    public function test_mail_renders_the_appointment_details(string $class, string $subject, array $expectedLines, string $sms): void
    {
        $mail = (new $class($this->appointment))->toMail($this->customer);

        $this->assertSame($subject, $mail->subject);

        $html = (string) $mail->render();
        foreach ($expectedLines as $line) {
            $this->assertStringContainsString($line, $html);
        }
    }

    /**
     * @param  class-string<AppointmentNotification>  $class
     */
    #[DataProvider('notifications')]
    public function test_sms_text_contains_the_appointment_details(string $class, string $subject, array $expectedLines, string $sms): void
    {
        $this->assertSame($this->withCancelUrl($sms), (new $class($this->appointment))->toSms($this->customer));
    }

    /**
     * @param  class-string<AppointmentNotification>  $class
     */
    #[DataProvider('notifications')]
    public function test_it_is_delivered_by_mail_and_sms(string $class, string $subject, array $expectedLines, string $sms): void
    {
        $this->customer->notifyNow(new $class($this->appointment));

        $sent = $this->sentEmails();
        $this->assertCount(1, $sent);
        $this->assertSame($subject, $sent[0]->getSubject());
        $this->assertSame($this->customer->email, $sent[0]->getTo()[0]->getAddress());

        $this->assertSame([['to' => '+15551234567', 'message' => $this->withCancelUrl($sms)]], $this->sms->sent);
    }

    private function withCancelUrl(string $sms): string
    {
        return str_replace('{cancel_url}', url('/cancel/the-cancel-token'), $sms);
    }

    /**
     * @param  class-string<AppointmentNotification>  $class
     */
    #[DataProvider('notifications')]
    public function test_it_is_queued_after_the_surrounding_transaction_commits(string $class, string $subject, array $expectedLines, string $sms): void
    {
        Queue::fake();

        $notification = new $class($this->appointment);
        $this->customer->notify($notification);

        $this->assertTrue($notification->afterCommit);
        $this->assertTrue($notification->deleteWhenMissingModels);
        Queue::assertPushed(
            SendQueuedNotifications::class,
            fn (SendQueuedNotifications $job): bool => $job->notification instanceof $class,
        );
    }

    public function test_sms_is_skipped_for_customers_without_a_phone(): void
    {
        $this->customer->update(['phone' => null]);
        $notification = new AppointmentConfirmed($this->appointment);

        $this->assertSame(['mail'], $notification->via($this->customer));

        $this->customer->notifyNow($notification);
        $this->assertCount(1, $this->sentEmails());
        $this->assertSame([], $this->sms->sent);
    }

    public function test_passwordless_customers_without_an_email_only_get_sms(): void
    {
        $this->customer->update(['email' => null]);
        $notification = new AppointmentConfirmed($this->appointment);

        $this->assertSame([SmsChannel::class], $notification->via($this->customer));

        $this->customer->notifyNow($notification);
        $this->assertCount(0, $this->sentEmails());
        $this->assertCount(1, $this->sms->sent);
    }

    public function test_customers_with_a_phone_are_routed_to_both_channels(): void
    {
        $this->assertSame(['mail', SmsChannel::class], (new AppointmentConfirmed($this->appointment))->via($this->customer));
    }

    public function test_the_staff_line_is_omitted_when_no_staff_member_is_assigned(): void
    {
        $this->appointment->update(['staff_member_id' => null]);

        $html = (string) (new AppointmentConfirmed($this->appointment->fresh()))->toMail($this->customer)->render();

        $this->assertStringNotContainsString('With:', $html);
    }

    public function test_a_reminder_is_not_delivered_if_the_appointment_was_canceled_meanwhile(): void
    {
        $this->appointment->update(['status' => AppointmentStatus::Canceled]);

        $this->customer->notifyNow(new AppointmentReminder($this->appointment));

        $this->assertSame([], $this->sentEmails());
        $this->assertSame([], $this->sms->sent);
    }

    public function test_a_reminder_is_not_delivered_once_the_appointment_has_started(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01 12:30'));

        $this->customer->notifyNow(new AppointmentReminder($this->appointment));

        $this->assertSame([], $this->sentEmails());
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages()->map(fn ($message) => $message->getOriginalMessage())->values()->all();
    }
}
