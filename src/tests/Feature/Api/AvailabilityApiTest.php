<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

class AvailabilityApiTest extends BookingTestCase
{
    private function availability(Service $service, array $query = []): TestResponse
    {
        return $this->getJson(
            "/api/tenants/{$this->tenant->slug}/services/{$service->id}/availability?"
            .http_build_query(['date' => self::MONDAY, ...$query]),
        );
    }

    /**
     * @return array<string, list<int>> start time => free staff ids
     */
    private function slots(Service $service, array $query = []): array
    {
        return collect($this->availability($service, $query)->assertOk()->json('data'))
            ->mapWithKeys(fn (array $slot): array => [substr($slot['start_time'], 11) => $slot['staff_member_ids']])
            ->all();
    }

    public function test_it_lists_the_slot_grid_within_business_hours(): void
    {
        $response = $this->availability($this->haircut)->assertOk();

        $response->assertJsonPath('data.0', [
            'start_time' => self::MONDAY.' 09:00',
            'end_time' => self::MONDAY.' 09:30',
            'available' => true,
            'staff_member_ids' => [$this->anna->id, $this->ben->id],
        ]);
        $this->assertSame(
            ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'],
            array_keys($this->slots($this->haircut)),
        );
    }

    public function test_overlaps_are_checked_per_staff_member(): void
    {
        $this->book($this->anna, '10:00');

        $this->assertSame([$this->ben->id], $this->slots($this->haircut)['10:00']);

        $this->book($this->ben, '10:00');

        $this->availability($this->haircut)->assertJsonPath('data.2', [
            'start_time' => self::MONDAY.' 10:00',
            'end_time' => self::MONDAY.' 10:30',
            'available' => false,
            'staff_member_ids' => [],
        ]);
    }

    public function test_longer_services_are_blocked_by_partial_overlaps_but_not_back_to_back(): void
    {
        $this->book($this->anna, '10:00');

        // VIP (60 min) is only performed by Anna; last start is 11:00 to finish by 12:00.
        $this->assertSame([
            '09:00' => [$this->anna->id],
            '09:30' => [],
            '10:00' => [],
            '10:30' => [$this->anna->id],
            '11:00' => [$this->anna->id],
        ], $this->slots($this->vip));
    }

    public function test_canceled_and_unassigned_appointments_do_not_block(): void
    {
        $this->book($this->anna, '09:00')->update(['status' => 'canceled']);
        Appointment::factory()->forService($this->haircut)
            ->at(CarbonImmutable::parse(self::MONDAY.' 09:00'))->create(['staff_member_id' => null]);

        $this->assertSame([$this->anna->id, $this->ben->id], $this->slots($this->haircut)['09:00']);
    }

    public function test_other_tenants_bookings_do_not_block(): void
    {
        $other = StaffMember::factory()->performing(
            Service::factory()->for(Tenant::factory()->withStandardHours())->create(),
        )->create();
        Appointment::factory()->forStaff($other)->at(CarbonImmutable::parse(self::MONDAY.' 09:00'))->create();

        $this->assertCount(2, $this->slots($this->haircut)['09:00']);
    }

    public function test_it_can_be_filtered_to_one_staff_member(): void
    {
        $this->book($this->ben, '09:00');

        $slots = $this->slots($this->haircut, ['staff_member_id' => $this->ben->id]);

        $this->assertSame([], $slots['09:00']);
        $this->assertSame([$this->ben->id], $slots['09:30']);
    }

    public function test_filtering_by_an_unqualified_staff_member_is_rejected(): void
    {
        $this->availability($this->vip, ['staff_member_id' => $this->ben->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_member_id');
    }

    public function test_inactive_staff_are_excluded(): void
    {
        $this->ben->update(['is_active' => false]);

        $this->assertSame([$this->anna->id], $this->slots($this->haircut)['09:00']);
    }

    public function test_a_service_without_qualified_staff_has_no_slots(): void
    {
        $service = Service::factory()->for($this->tenant)->create();

        $this->availability($service)->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_closed_days_have_no_slots(): void
    {
        $this->availability($this->haircut, ['date' => '2026-10-04']) // Sunday
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_slots_that_have_already_started_are_hidden(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 10:10'));

        $this->assertSame(['10:30', '11:00', '11:30'], array_keys($this->slots($this->haircut)));
    }

    public function test_date_is_validated(): void
    {
        $this->availability($this->haircut, ['date' => ''])->assertJsonValidationErrors('date');
        $this->availability($this->haircut, ['date' => '28-09-2026'])->assertJsonValidationErrors('date');
        $this->availability($this->haircut, ['date' => '2026-09-27'])->assertJsonValidationErrors('date');
        $this->availability($this->haircut, ['date' => '2026-12-01'])->assertJsonValidationErrors('date');
    }

    public function test_services_are_scoped_to_the_tenant_in_the_url(): void
    {
        $foreign = Service::factory()->create();

        $this->availability($foreign)->assertNotFound();
        $this->getJson("/api/tenants/unknown-shop/services/{$this->haircut->id}/availability?date=".self::MONDAY)
            ->assertNotFound();
    }

    public function test_inactive_services_are_not_bookable(): void
    {
        $this->haircut->update(['is_active' => false]);

        $this->availability($this->haircut)->assertNotFound();
    }

    public function test_query_count_does_not_grow_with_staff_or_appointments(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->availability($this->haircut)->assertOk();

            return count(DB::getQueryLog());
        };

        $baseline = $countQueries();

        StaffMember::factory(5)->performing($this->haircut)->create();
        foreach (['09:00', '10:00', '11:00'] as $time) {
            $this->book($this->anna, $time);
            $this->book($this->ben, $time);
        }

        $this->assertSame($baseline, $countQueries());
    }
}
