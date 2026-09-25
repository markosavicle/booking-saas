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
    ) {}

    /**
     * Books $start for $customer. The tenant is always derived from the service.
     * When no staff member is requested, the least busy free one is assigned.
     *
     * @throws ValidationException When the time is not a bookable slot or the staff member is unqualified.
     * @throws BookingConflictException When the slot was taken concurrently.
     */
    public function execute(User $customer, Service $service, CarbonImmutable $start, ?StaffMember $staff = null): Appointment
    {
        $slot = $this->availableSlots->execute($service, $start, $staff)
            ->first(fn (Slot $slot): bool => $slot->start->equalTo($start));

        if ($slot === null) {
            throw ValidationException::withMessages([
                'start_time' => 'The selected time is outside business hours or not a bookable slot.',
            ]);
        }

        $appointment = DB::transaction(function () use ($customer, $service, $slot, $staff): Appointment {
            $candidateIds = $staff !== null
                ? [$staff->id]
                : $service->qualifiedStaff()->pluck('staff_members.id')->all();

            // Serialise concurrent bookings per staff member. Locking the staff rows
            // (rather than appointment rows) also covers staff with no appointments yet.
            StaffMember::query()->whereKey($candidateIds)->orderBy('id')->lockForUpdate()->get(['id']);

            $staffId = $this->leastBusyFreeStaff($candidateIds, $slot)
                ?? throw BookingConflictException::slotTaken();

            return Appointment::create([
                'tenant_id' => $service->tenant_id,
                'service_id' => $service->id,
                'staff_member_id' => $staffId,
                'user_id' => $customer->id,
                'start_time' => $slot->start,
                'end_time' => $slot->end,
                'status' => AppointmentStatus::Confirmed,
            ]);
        });

        $customer->notify(new AppointmentConfirmed($appointment));

        return $appointment->load(['tenant', 'service', 'staffMember']);
    }

    /**
     * @param  list<int>  $candidateIds
     */
    private function leastBusyFreeStaff(array $candidateIds, Slot $slot): ?int
    {
        $blocking = Appointment::query()
            ->blocking()
            ->whereIn('staff_member_id', $candidateIds)
            ->overlapping($slot->start->startOfDay(), $slot->start->endOfDay())
            ->get(['staff_member_id', 'start_time', 'end_time']);

        $busyNow = $blocking->filter->overlaps($slot->start, $slot->end)->pluck('staff_member_id')->unique();
        $loadPerStaff = $blocking->countBy('staff_member_id');

        return collect($candidateIds)
            ->diff($busyNow)
            ->sortBy(fn (int $id): array => [$loadPerStaff->get($id, 0), $id])
            ->first();
    }
}
