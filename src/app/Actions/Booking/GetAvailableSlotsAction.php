<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Data\Slot;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds the bookable grid for a service on a given day and marks which
 * qualified staff members are free for each slot. Runs a fixed number of
 * queries regardless of staff or appointment count.
 */
final readonly class GetAvailableSlotsAction
{
    /**
     * @return Collection<int, Slot> Every future slot inside business hours,
     *                               including fully booked ones (empty staffIds).
     *
     * @throws ValidationException When $staff cannot perform the service.
     */
    public function execute(Service $service, CarbonImmutable $date, ?StaffMember $staff = null): Collection
    {
        $hours = $service->tenant->hoursFor($date);

        if ($hours === null) {
            return collect();
        }

        $staffIds = $this->qualifiedStaffIds($service, $staff);

        if ($staffIds === []) {
            return collect();
        }

        $opensAt = $hours->opensOn($date);
        $closesAt = $hours->closesOn($date);

        $busyByStaff = Appointment::query()
            ->blocking()
            ->whereIn('staff_member_id', $staffIds)
            ->overlapping($opensAt, $closesAt)
            ->get(['staff_member_id', 'start_time', 'end_time'])
            ->groupBy('staff_member_id');

        $interval = (int) config('booking.slot_interval_minutes');
        $duration = $service->duration_minutes;
        $now = CarbonImmutable::now();
        $slots = collect();

        for ($start = $opensAt; $start->addMinutes($duration) <= $closesAt; $start = $start->addMinutes($interval)) {
            if ($start <= $now) {
                continue;
            }

            $end = $start->addMinutes($duration);

            $freeStaffIds = array_values(array_filter(
                $staffIds,
                fn (int $id): bool => ! ($busyByStaff->get($id)?->contains->overlaps($start, $end) ?? false),
            ));

            $slots->push(new Slot($start, $end, $freeStaffIds));
        }

        return $slots;
    }

    /**
     * @return list<int>
     */
    private function qualifiedStaffIds(Service $service, ?StaffMember $staff): array
    {
        $ids = $service->qualifiedStaff()
            ->when($staff, fn ($query) => $query->whereKey($staff->id))
            ->orderBy('staff_members.id')
            ->pluck('staff_members.id')
            ->all();

        if ($staff !== null && $ids === []) {
            throw ValidationException::withMessages([
                'staff_member_id' => 'The selected staff member does not perform this service.',
            ]);
        }

        return $ids;
    }
}
