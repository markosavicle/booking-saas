<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeSmsSender;

class GuestBookingOtpTest extends BookingTestCase
{
    private const string PHONE = '+381601234567';

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requestCode(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/booking-requests', [
            'service_id' => $this->haircut->id,
            'start_time' => self::MONDAY.' 09:00',
            'name' => 'Marko Petrović',
            'phone' => self::PHONE,
            'email' => 'marko@example.com',
            ...$overrides,
        ]);
    }

    private function confirm(string $id, string $code): TestResponse
    {
        return $this->postJson("/api/booking-requests/{$id}/confirm", ['code' => $code]);
    }

    private function lastCode(): string
    {
        $this->assertNotEmpty($this->sms->sent, 'No SMS was sent.');
        preg_match('/\b(\d{6})\b/', end($this->sms->sent)['message'], $match);

        return $match[1];
    }

    private function wrongCode(): string
    {
        return $this->lastCode() === '000000' ? '111111' : '000000';
    }

    public function test_requesting_a_code_texts_it_and_saves_nothing_yet(): void
    {
        $this->requestCode(['phone' => '+381 60 123-4567'])
            ->assertAccepted()
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.expires_in', 600)
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame(self::PHONE, $this->sms->sent[0]['to']);
        $this->assertMatchesRegularExpression('/^\d{6} is your .+ booking code\. It expires in 10 minutes\./', $this->sms->sent[0]['message']);

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_the_correct_code_creates_the_customer_and_the_booking(): void
    {
        $id = $this->requestCode()->json('data.id');

        $response = $this->confirm($id, $this->lastCode())
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.start_time', self::MONDAY.' 09:00')
            ->assertJsonPath('data.service.id', $this->haircut->id);

        $appointment = Appointment::sole();
        $customer = User::sole();
        $this->assertSame(route('booking.cancel', $appointment->cancel_token), $response->json('meta.cancel_url'));
        $this->assertSame($customer->id, $appointment->user_id);
        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertSame(['Marko Petrović', self::PHONE, 'marko@example.com'], [$customer->name, $customer->phone, $customer->email]);
        $this->assertNull($customer->password);
        Notification::assertSentToTimes($customer, AppointmentConfirmed::class, 1);
    }

    public function test_email_is_optional(): void
    {
        $id = $this->requestCode(['email' => ''])->assertAccepted()->json('data.id');
        $this->confirm($id, $this->lastCode())->assertCreated();

        $this->assertNull(User::sole()->email);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidDetails(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'missing phone' => [['phone' => ''], 'phone'],
            'phone without country code' => [['phone' => '060 123 4567'], 'phone'],
            'malformed email' => [['email' => 'not-an-email'], 'email'],
            'past time' => [['start_time' => self::MONDAY.' 07:00'], 'start_time'],
            'outside business hours' => [['start_time' => self::MONDAY.' 13:00'], 'start_time'],
            'unknown service' => [['service_id' => 999], 'service_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidDetails')]
    public function test_invalid_details_are_rejected_without_sending_an_sms(array $overrides, string $field): void
    {
        $this->requestCode($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame([], $this->sms->sent);
    }

    public function test_no_code_is_sent_for_a_slot_that_is_already_taken(): void
    {
        $this->book($this->anna, '09:00');
        $this->book($this->ben, '09:00');

        $this->requestCode()->assertConflict()->assertJsonPath('message', 'The selected time slot is no longer available.');
        $this->assertSame([], $this->sms->sent);
    }

    public function test_a_wrong_code_books_nothing_and_the_right_one_still_works(): void
    {
        $id = $this->requestCode()->json('data.id');

        $this->confirm($id, $this->wrongCode())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'incorrect']);
        $this->assertDatabaseCount('appointments', 0);

        $this->confirm($id, $this->lastCode())->assertCreated();
    }

    public function test_the_code_is_invalidated_after_too_many_wrong_attempts(): void
    {
        $id = $this->requestCode()->json('data.id');

        foreach (range(1, 4) as $attempt) {
            $this->confirm($id, $this->wrongCode())->assertJsonValidationErrors(['code' => 'incorrect']);
        }
        $this->confirm($id, $this->wrongCode())->assertJsonValidationErrors(['code' => 'Too many incorrect attempts']);

        $this->confirm($id, $this->lastCode())->assertJsonValidationErrors(['code' => 'expired']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $id = $this->requestCode()->json('data.id');
        $code = $this->lastCode();

        $this->confirm($id, $code)->assertCreated();
        $this->confirm($id, $code)->assertJsonValidationErrors(['code' => 'expired']);

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_codes_expire(): void
    {
        $id = $this->requestCode()->json('data.id');
        $this->travel(11)->minutes();

        $this->confirm($id, $this->lastCode())->assertJsonValidationErrors(['code' => 'expired']);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_wrong_guesses_do_not_extend_a_codes_lifetime(): void
    {
        $id = $this->requestCode()->json('data.id');
        $this->travel(9)->minutes();
        $this->confirm($id, $this->wrongCode());
        $this->travel(2)->minutes();

        $this->confirm($id, $this->lastCode())->assertJsonValidationErrors(['code' => 'expired']);
    }

    public function test_codes_belong_to_their_own_request(): void
    {
        $first = $this->requestCode()->json('data.id');
        $this->requestCode(['start_time' => self::MONDAY.' 10:00', 'phone' => '+381609999999']);

        // The second request's code does not unlock the first.
        $this->confirm($first, $this->lastCode())->assertJsonValidationErrors('code');
    }

    public function test_the_slot_is_rechecked_when_the_code_is_confirmed(): void
    {
        $id = $this->requestCode(['staff_member_id' => $this->anna->id])->json('data.id');
        $this->book($this->anna, '09:00'); // taken while the customer was reading the SMS

        $this->confirm($id, $this->lastCode())->assertConflict();
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_returning_customers_are_recognised_by_phone(): void
    {
        $existing = User::factory()->create(['phone' => self::PHONE, 'email' => 'old@example.com', 'name' => 'M. P.']);

        $id = $this->requestCode()->json('data.id');
        $this->confirm($id, $this->lastCode())->assertCreated();

        $this->assertSame(1, User::count());
        $existing->refresh();
        $this->assertSame('Marko Petrović', $existing->name);
        $this->assertSame('old@example.com', $existing->email, 'A verified phone must not replace an existing e-mail.');
        $this->assertSame($existing->id, Appointment::sole()->user_id);
    }

    public function test_an_email_owned_by_another_account_is_not_claimed(): void
    {
        $other = User::factory()->tenantAdmin($this->tenant)->create(['email' => 'marko@example.com']);

        $id = $this->requestCode()->json('data.id');
        $this->confirm($id, $this->lastCode())->assertCreated();

        $customer = User::query()->where('phone', self::PHONE)->sole();
        $this->assertNull($customer->email);
        $this->assertNotSame($other->id, Appointment::sole()->user_id);
    }

    public function test_staff_accounts_keep_their_profile_when_they_book_as_a_guest(): void
    {
        $admin = User::factory()->tenantAdmin($this->tenant)->create(['phone' => self::PHONE, 'name' => 'Shop Owner']);

        $id = $this->requestCode(['name' => 'Someone Else'])->json('data.id');
        $this->confirm($id, $this->lastCode())->assertCreated();

        $this->assertSame('Shop Owner', $admin->fresh()->name);
        $this->assertSame(UserRole::TenantAdmin, $admin->fresh()->role);
    }

    public function test_a_booking_inside_the_reminder_window_is_already_marked_reminded(): void
    {
        $id = $this->requestCode()->json('data.id');
        $this->confirm($id, $this->lastCode())->assertCreated();

        $this->assertNotNull(Appointment::sole()->reminder_sent_at);
        $this->assertSame(AppointmentStatus::Confirmed, Appointment::sole()->status);
    }

    public function test_the_code_must_be_six_digits_and_the_id_a_uuid(): void
    {
        $id = $this->requestCode()->json('data.id');

        $this->confirm($id, '12345')->assertJsonValidationErrors('code');
        $this->confirm($id, 'abcdef')->assertJsonValidationErrors('code');
        $this->postJson('/api/booking-requests/not-a-uuid/confirm', ['code' => '123456'])->assertNotFound();
    }
}
