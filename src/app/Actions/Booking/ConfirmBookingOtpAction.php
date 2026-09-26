<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Actions\Customer\ResolveCustomerAction;
use App\Exceptions\BookingConflictException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Services\BookingOtpBroker;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmBookingOtpAction
{
    public function __construct(
        private BookingOtpBroker $otp,
        private ResolveCustomerAction $resolveCustomer,
        private CreateBookingAction $createBooking,
    ) {}

    /**
     * Redeems the code and only then creates the customer and the appointment.
     *
     * @throws ValidationException When the code is wrong/expired or the draft is no longer bookable.
     * @throws BookingConflictException When the slot was taken while the code was pending.
     */
    public function execute(string $verificationId, string $code): Appointment
    {
        $draft = $this->otp->redeem($verificationId, $code);

        $service = Service::query()->where('is_active', true)->with('tenant.businessHours')->find($draft->serviceId)
            ?? throw ValidationException::withMessages(['service_id' => 'This service is no longer available.']);

        $staff = $draft->staffMemberId === null
            ? null
            : StaffMember::query()->where('is_active', true)->find($draft->staffMemberId)
                ?? throw ValidationException::withMessages(['staff_member_id' => 'This staff member is no longer available.']);

        $customer = $this->resolveCustomer->execute($draft->phone, $draft->name, $draft->email);

        // The account may not take an e-mail another account owns, but this booking's mail still goes there.
        return $this->createBooking->execute($customer, $service, $draft->start, $staff, $draft->email);
    }
}
