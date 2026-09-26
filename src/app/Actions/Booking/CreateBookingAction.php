<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Data\Slot;
use App\Enums\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateBookingAction
{
    public function __construct(
        private GetAvailableSlotsAction $availableSlots,
        private ActiveBookingGuard $activeBookings,
    ) {}

    /**
     * Books $start (any timezone; stored as UTC) for $customer. The tenant is always
     * derived from the service. When no staff member is requested, the least busy
     * free one is assigned.
     *
     * @throws ValidationException When the time is not a bookable slot, the staff member is unqualified,
     *                             or the customer already has an upcoming booking at this shop.
     * @throws BookingConflictException When the slot was taken concurrently.
     */
    public function execute(User $customer, Service $service, CarbonImmutable $start, ?StaffMember $staff = null): Appointment
    {
        $localDay = $start->setTimezone($service->tenant->timezone)->startOfDay();
        $slot = $this->bookableSlot($service, $start, $staff);

        $appointment = DB::transaction(function () use ($customer, $service, $slot, $staff, $localDay): Appointment {
            // Lock the customer first so two pending codes for one phone can't both become bookings.
            User::query()->whereKey($customer->id)->lockForUpdate()->first(['id']);
            $this->activeBookings->ensureNoneFor($customer, $service->tenant_id);

            $candidateIds = $staff !== null
                ? [$staff->id]
                : $service->qualifiedStaff()->pluck('staff_members.id')->all();

            // Serialise concurrent bookings per staff member. Locking the staff rows
            // (rather than appointment rows) also covers staff with no appointments yet.
            StaffMember::query()->whereKey($candidateIds)->orderBy('id')->lockForUpdate()->get(['id']);

            $staffId = $this->leastBusyFreeStaff($candidateIds, $slot, $localDay)
                ?? throw BookingConflictException::slotTaken();

            return Appointment::create([
                'tenant_id' => $service->tenant_id,
                'service_id' => $service->id,
                'staff_member_id' => $staffId,
                'user_id' => $customer->id,
                'start_time' => $slot->start,
                'end_time' => $slot->end,
                'status' => AppointmentStatus::Confirmed,
                // The confirmation already covers bookings inside the reminder window.
                'reminder_sent_at' => $this->withinReminderWindow($slot) ? now() : null,
            ]);
        });

        $customer->notify(new AppointmentConfirmed($appointment));

        return $appointment->load(['tenant', 'service', 'staffMember']);
    }

    /**
     * The free slot starting at $start. Checked again under lock when booking.
     *
     * @throws ValidationException
     */
    public function bookableSlot(Service $service, CarbonImmutable $start, ?StaffMember $staff = null): Slot
    {
        $localDay = $start->setTimezone($service->tenant->timezone)->startOfDay();

        return $this->availableSlots->execute($service, $localDay, $staff)
            ->first(fn (Slot $slot): bool => $slot->start->equalTo($start))
            ?? throw ValidationException::withMessages([
                'start_time' => 'The selected time is outside business hours or not a bookable slot.',
            ]);
    }

    private function withinReminderWindow(Slot $slot): bool
    {
        return $slot->start->lessThanOrEqualTo(now()->addHours((int) config('booking.reminder_lead_hours')));
    }

    /**
     * @param  list<int>  $candidateIds
     */
    private function leastBusyFreeStaff(array $candidateIds, Slot $slot, CarbonImmutable $localDay): ?int
    {
        // "Busiest" is measured over the shop's calendar day, bound to the query as UTC.
        $blocking = Appointment::query()
            ->blocking()
            ->whereIn('staff_member_id', $candidateIds)
            ->overlapping($localDay->utc(), $localDay->endOfDay()->utc())
            ->get(['staff_member_id', 'start_time', 'end_time']);

        $busyNow = $blocking->filter->overlaps($slot->start, $slot->end)->pluck('staff_member_id')->unique();
        $loadPerStaff = $blocking->countBy('staff_member_id');

        return collect($candidateIds)
            ->diff($busyNow)
            ->sortBy(fn (int $id): array => [$loadPerStaff->get($id, 0), $id])
            ->first();
    }
}
