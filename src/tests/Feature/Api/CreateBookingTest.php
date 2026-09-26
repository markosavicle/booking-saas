<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Booking\ActiveBookingGuard;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;

class CreateBookingTest extends BookingTestCase
{
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
    }

    private function bookVia(array $payload, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->customer)->postJson('/api/bookings', [
            'service_id' => $this->haircut->id,
            'start_time' => self::MONDAY.' 09:00',
            ...$payload,
        ]);
    }

    public function test_a_customer_books_a_slot_and_a_confirmation_is_queued_once(): void
    {
        $this->bookVia([])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.start_time', self::MONDAY.' 09:00')
            ->assertJsonPath('data.end_time', self::MONDAY.' 09:30')
            ->assertJsonPath('data.tenant.slug', $this->tenant->slug)
            ->assertJsonPath('data.service.id', $this->haircut->id)
            ->assertJsonPath('data.staff_member.id', $this->anna->id);

        $appointment = Appointment::sole();
        $this->assertSame($this->tenant->id, $appointment->tenant_id, 'Tenant must come from the service, not the user.');
        $this->assertSame($this->customer->id, $appointment->user_id);
        Notification::assertSentToTimes($this->customer, AppointmentConfirmed::class, 1);
        Notification::assertSentTo(
            $this->customer,
            fn (AppointmentConfirmed $notification): bool => $notification->appointment->is($appointment),
        );
    }

    public function test_the_requested_staff_member_is_honoured(): void
    {
        $this->bookVia(['staff_member_id' => $this->ben->id])
            ->assertCreated()
            ->assertJsonPath('data.staff_member.id', $this->ben->id);
    }

    public function test_a_free_staff_member_is_assigned_when_another_is_busy(): void
    {
        $this->book($this->anna, '09:00');

        $this->bookVia([])->assertCreated()->assertJsonPath('data.staff_member.id', $this->ben->id);
    }

    public function test_the_least_busy_free_staff_member_is_assigned(): void
    {
        $this->book($this->anna, '11:00');

        $this->bookVia([])->assertCreated()->assertJsonPath('data.staff_member.id', $this->ben->id);
    }

    public function test_it_returns_409_when_every_qualified_staff_member_is_busy(): void
    {
        $this->book($this->anna, '09:00');
        $this->book($this->ben, '09:00');

        $this->bookVia([])
            ->assertConflict()
            ->assertExactJson(['message' => 'The selected time slot is no longer available.']);

        $this->assertSame(2, Appointment::count());
        Notification::assertNothingSent();
    }

    public function test_it_returns_409_when_the_requested_staff_member_is_busy(): void
    {
        $this->book($this->ben, '09:00');

        $this->bookVia(['staff_member_id' => $this->ben->id])->assertConflict();
    }

    public function test_partial_overlap_with_a_longer_service_conflicts(): void
    {
        $this->book($this->anna, '09:30');

        // VIP 09:00–10:00 overlaps Anna's 09:30, and only Anna performs VIP.
        $this->bookVia(['service_id' => $this->vip->id])->assertConflict();
    }

    public function test_back_to_back_bookings_are_allowed(): void
    {
        $this->book($this->anna, '09:30');

        $this->bookVia(['staff_member_id' => $this->anna->id])->assertCreated();
        // Another customer: one customer may only hold one upcoming booking per shop.
        $this->bookVia(['staff_member_id' => $this->anna->id, 'start_time' => self::MONDAY.' 10:00'], User::factory()->create())->assertCreated();
    }

    public function test_a_customer_can_hold_only_one_upcoming_booking_per_shop(): void
    {
        $this->bookVia(['staff_member_id' => $this->anna->id])->assertCreated();

        $this->bookVia(['staff_member_id' => $this->ben->id, 'start_time' => self::MONDAY.' 11:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => ActiveBookingGuard::MESSAGE]);
    }

    public function test_canceled_appointments_free_the_slot(): void
    {
        $this->book($this->anna, '09:00')->update(['status' => AppointmentStatus::Canceled]);

        $this->bookVia(['staff_member_id' => $this->anna->id])->assertCreated();
    }

    public function test_an_unqualified_staff_member_is_rejected(): void
    {
        $this->bookVia(['service_id' => $this->vip->id, 'staff_member_id' => $this->ben->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_member_id');
    }

    public function test_staff_from_another_tenant_is_rejected(): void
    {
        $foreignStaff = StaffMember::factory()->performing(Service::factory()->create())->create();

        $this->bookVia(['staff_member_id' => $foreignStaff->id])->assertJsonValidationErrors('staff_member_id');
    }

    public static function unbookableTimes(): array
    {
        return [
            'before opening' => [self::MONDAY.' 08:30'],
            'runs past closing' => [self::MONDAY.' 11:45'],
            'after closing' => [self::MONDAY.' 12:00'],
            'off the slot grid' => [self::MONDAY.' 09:10'],
            'closed day' => ['2026-10-04 10:00'],
        ];
    }

    #[DataProvider('unbookableTimes')]
    public function test_times_that_are_not_bookable_slots_are_rejected(string $startTime): void
    {
        $this->bookVia(['start_time' => $startTime])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->assertSame(0, Appointment::count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing service' => [['service_id' => null], 'service_id'],
            'unknown service' => [['service_id' => 999], 'service_id'],
            'missing start' => [['start_time' => null], 'start_time'],
            'bad format' => [['start_time' => '2026-09-28T09:00:00Z'], 'start_time'],
            'in the past' => [['start_time' => '2026-09-26 09:00'], 'start_time'],
            'too far ahead' => [['start_time' => '2027-01-04 09:00'], 'start_time'],
            'unknown staff' => [['staff_member_id' => 999], 'staff_member_id'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_are_rejected(array $payload, string $field): void
    {
        $this->bookVia($payload)->assertUnprocessable()->assertJsonValidationErrors($field);

        Notification::assertNothingSent();
    }

    public function test_inactive_services_cannot_be_booked(): void
    {
        $this->haircut->update(['is_active' => false]);

        $this->bookVia([])->assertJsonValidationErrors('service_id');
    }

    public function test_guests_cannot_book(): void
    {
        $this->postJson('/api/bookings', ['service_id' => $this->haircut->id, 'start_time' => self::MONDAY.' 09:00'])
            ->assertUnauthorized();
    }
}
