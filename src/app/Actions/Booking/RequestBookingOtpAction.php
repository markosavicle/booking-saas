<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Data\BookingDraft;
use App\Exceptions\BookingConflictException;
use App\Models\Service;
use App\Models\StaffMember;
use App\Services\BookingOtpBroker;
use Illuminate\Validation\ValidationException;

final readonly class RequestBookingOtpAction
{
    public function __construct(
        private CreateBookingAction $createBooking,
        private BookingOtpBroker $otp,
        private ActiveBookingGuard $activeBookings,
    ) {}

    /**
     * Texts a code for $draft and returns the verification id. The slot is checked
     * first so no SMS is spent on a time that cannot be booked; nothing is held.
     *
     * @throws ValidationException When the phone already has an upcoming booking here, or the time is not a slot at all.
     * @throws BookingConflictException When nobody is free at that time.
     */
    public function execute(BookingDraft $draft, Service $service, ?StaffMember $staff): string
    {
        $this->activeBookings->ensureNoneForPhone($draft->phone, $service->tenant_id);

        if (! $this->createBooking->bookableSlot($service, $draft->start, $staff)->isAvailable()) {
            throw BookingConflictException::slotTaken();
        }

        return $this->otp->issue($draft, $service->tenant->name);
    }
}
