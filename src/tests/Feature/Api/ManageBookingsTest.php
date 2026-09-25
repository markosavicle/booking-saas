<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

class ManageBookingsTest extends BookingTestCase
{
    private User $customer;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
        $this->appointment = $this->book($this->anna, '09:00');
        $this->appointment->update(['user_id' => $this->customer->id]);
    }

    private function cancel(User $as): TestResponse
    {
        return $this->actingAs($as)->postJson("/api/bookings/{$this->appointment->id}/cancel");
    }

    public function test_customers_can_cancel_their_own_booking_which_frees_the_slot(): void
    {
        $this->cancel($this->customer)
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');

        $this->assertSame(AppointmentStatus::Canceled, $this->appointment->fresh()->status);
        $this->getJson("/api/tenants/{$this->tenant->slug}/services/{$this->haircut->id}/availability?date=".self::MONDAY)
            ->assertJsonPath('data.0.staff_member_ids', [$this->anna->id, $this->ben->id]);
    }

    public function test_customers_cannot_cancel_someone_elses_booking(): void
    {
        $this->cancel(User::factory()->create())->assertForbidden();
    }

    public function test_the_tenants_admin_can_cancel_but_other_tenant_admins_cannot_see_it(): void
    {
        $this->cancel(User::factory()->tenantAdmin($this->tenant)->create())->assertOk();

        $this->appointment->update(['status' => AppointmentStatus::Confirmed]);
        $this->cancel(User::factory()->tenantAdmin(Tenant::factory()->create())->create())->assertNotFound();
    }

    public function test_canceling_twice_conflicts(): void
    {
        $this->cancel($this->customer)->assertOk();
        $this->cancel($this->customer)->assertConflict();
    }

    public function test_started_appointments_cannot_be_canceled(): void
    {
        $this->travelTo(CarbonImmutable::parse(self::MONDAY.' 09:05'));

        $this->cancel($this->customer)->assertConflict();
        $this->assertSame(AppointmentStatus::Confirmed, $this->appointment->fresh()->status);
    }

    public function test_guests_cannot_cancel(): void
    {
        $this->postJson("/api/bookings/{$this->appointment->id}/cancel")->assertUnauthorized();
    }

    public function test_customers_list_only_their_own_bookings_without_n_plus_one(): void
    {
        $this->book($this->ben, '10:00'); // someone else's
        foreach (['10:30', '11:00'] as $time) {
            $this->book($this->anna, $time)->update(['user_id' => $this->customer->id]);
        }

        DB::enableQueryLog();
        $response = $this->actingAs($this->customer)->getJson('/api/bookings')->assertOk();
        $queries = count(DB::getQueryLog());

        $response->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.start_time', self::MONDAY.' 11:00')
            ->assertJsonPath('data.0.staff_member.name', 'Anna')
            ->assertJsonPath('data.0.service.name', 'Haircut')
            ->assertJsonPath('meta.total', 3);
        // count + page + tenant/service/staff eager loads
        $this->assertLessThanOrEqual(5, $queries);
    }
}
