<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * The shop is in Belgrade (UTC+2 on these dates) and open 09:00–12:00 local,
 * i.e. 07:00–10:00 UTC. The API speaks local wall-clock time; the DB stores UTC.
 */
class TimezoneTest extends BookingTestCase
{
    private const string TUESDAY = '2026-09-29';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant->update(['timezone' => 'Europe/Belgrade']);
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 05:00', 'UTC')); // 07:00 in Belgrade
    }

    private function availability(string $date = self::MONDAY): TestResponse
    {
        return $this->getJson("/api/tenants/{$this->tenant->slug}/services/{$this->haircut->id}/availability?date={$date}");
    }

    private function bookAt(string $localTime): TestResponse
    {
        return $this->actingAs(User::factory()->create())->postJson('/api/bookings', [
            'service_id' => $this->haircut->id,
            'start_time' => $localTime,
        ]);
    }

    public function test_slots_follow_the_shops_business_hours_in_local_time(): void
    {
        $this->availability()
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.start_time', self::MONDAY.' 09:00')
            ->assertJsonPath('data.5.start_time', self::MONDAY.' 11:30')
            ->assertJsonPath('data.5.end_time', self::MONDAY.' 12:00')
            ->assertJsonPath('meta.timezone', 'Europe/Belgrade');
    }

    public function test_appointments_stored_in_utc_block_the_matching_local_slot(): void
    {
        $this->book($this->anna, '07:00'); // UTC, i.e. 09:00 in Belgrade

        $this->availability()
            ->assertJsonPath('data.0.start_time', self::MONDAY.' 09:00')
            ->assertJsonPath('data.0.staff_member_ids', [$this->ben->id])
            ->assertJsonPath('data.1.staff_member_ids', [$this->anna->id, $this->ben->id]);
    }

    public function test_bookings_are_accepted_in_local_time_and_stored_in_utc(): void
    {
        $this->bookAt(self::MONDAY.' 09:00')
            ->assertCreated()
            ->assertJsonPath('data.start_time', self::MONDAY.' 09:00')
            ->assertJsonPath('data.end_time', self::MONDAY.' 09:30')
            ->assertJsonPath('data.timezone', 'Europe/Belgrade');

        $this->assertSame(self::MONDAY.' 07:00:00', DB::table('appointments')->value('start_time'));
        $this->assertTrue(Appointment::sole()->start_time->equalTo(CarbonImmutable::parse(self::MONDAY.' 07:00', 'UTC')));
    }

    public function test_a_time_that_is_already_past_at_the_shop_is_rejected(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 08:00', 'UTC')); // 10:00 in Belgrade

        // 09:30 would still be in the future if it were read as UTC.
        $this->bookAt(self::MONDAY.' 09:30')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time' => 'must be in the future']);

        $this->availability()->assertJsonPath('data.0.start_time', self::MONDAY.' 10:30');
    }

    public function test_a_local_time_outside_business_hours_is_rejected_even_if_it_is_inside_them_in_utc(): void
    {
        // 08:30 in Belgrade is before opening; read as UTC it would be 10:30 local and bookable.
        $this->bookAt(self::MONDAY.' 08:30')->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_today_is_the_shops_today_not_the_servers(): void
    {
        // Monday 12:00 UTC is already Tuesday 01:00 in Auckland (UTC+13).
        $this->tenant->update(['timezone' => 'Pacific/Auckland']);
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 12:00', 'UTC'));

        $this->availability(self::MONDAY)->assertUnprocessable()->assertJsonValidationErrors('date');

        // Tuesday's hours apply (the local weekday), even though it is still Monday in UTC.
        $this->availability(self::TUESDAY)
            ->assertOk()
            ->assertJsonPath('data.0.start_time', self::TUESDAY.' 09:00')
            ->assertJsonPath('meta.timezone', 'Pacific/Auckland');

        $this->bookAt(self::TUESDAY.' 09:00')->assertCreated();
        $this->assertSame(self::MONDAY.' 20:00:00', DB::table('appointments')->value('start_time'));
    }

    public function test_booking_lists_show_local_times(): void
    {
        $customer = User::factory()->create();
        $this->book($this->anna, '07:30')->update(['user_id' => $customer->id]);

        $this->actingAs($customer)->getJson('/api/bookings')
            ->assertJsonPath('data.0.start_time', self::MONDAY.' 09:30')
            ->assertJsonPath('data.0.timezone', 'Europe/Belgrade');
    }

    public function test_business_hours_keep_their_wall_clock_time_across_a_dst_change(): void
    {
        $hours = new BusinessHour(['opens_at' => '09:00:00', 'closes_at' => '17:00:00']);

        // Belgrade leaves summer time (UTC+2 → UTC+1) on 25 October 2026.
        $saturday = CarbonImmutable::parse('2026-10-24', 'Europe/Belgrade');
        $sunday = CarbonImmutable::parse('2026-10-25', 'Europe/Belgrade');

        $this->assertSame('2026-10-24 07:00', $hours->opensOn($saturday)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-25 08:00', $hours->opensOn($sunday)->format('Y-m-d H:i'));
        $this->assertSame('UTC', $hours->closesOn($sunday)->getTimezone()->getName());
    }
}
