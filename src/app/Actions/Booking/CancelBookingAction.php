<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;

final readonly class CancelBookingAction
{
    /**
     * @throws BookingConflictException
     */
    public function execute(Appointment $appointment): Appointment
    {
        if ($appointment->status === AppointmentStatus::Canceled) {
            throw BookingConflictException::alreadyCanceled();
        }

        if ($appointment->start_time->isPast()) {
            throw BookingConflictException::alreadyStarted();
        }

        $appointment->update(['status' => AppointmentStatus::Canceled]);

        return $appointment;
    }
}
