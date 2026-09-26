<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Booking\CancelBookingAction;
use App\Enums\AppointmentStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Token-authorised cancellation from the link in the confirmation e-mail/SMS.
 *
 * GET only renders: mail and SMS link scanners open links automatically, so a
 * state-changing GET would cancel bookings nobody meant to cancel.
 */
class CancelBookingPageController extends Controller
{
    public function show(Appointment $appointment): View
    {
        $appointment->load(['tenant', 'service', 'staffMember']);

        return view('cancel', [
            'appointment' => $appointment,
            'local' => $appointment->start_time->setTimezone($appointment->tenant->timezone),
            'cancelable' => $appointment->status !== AppointmentStatus::Canceled && $appointment->start_time->isFuture(),
        ]);
    }

    public function destroy(Appointment $appointment, CancelBookingAction $cancelBooking): RedirectResponse
    {
        try {
            $cancelBooking->execute($appointment);
        } catch (BookingConflictException $e) {
            return to_route('booking.cancel', $appointment->cancel_token)->with('error', $e->getMessage());
        }

        return to_route('booking.cancel', $appointment->cancel_token)->with('canceled', true);
    }
}
