<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Booking\ConfirmBookingOtpAction;
use App\Actions\Booking\RequestBookingOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmBookingOtpRequest;
use App\Http\Requests\RequestBookingOtpRequest;
use App\Http\Resources\AppointmentResource;
use Illuminate\Http\JsonResponse;

/**
 * Passwordless guest booking: details in, SMS code out, code back in, booking created.
 */
class BookingRequestController extends Controller
{
    public function store(RequestBookingOtpRequest $request, RequestBookingOtpAction $requestOtp): JsonResponse
    {
        $draft = $request->draft();
        $id = $requestOtp->execute($draft, $request->service(), $request->staffMember());

        return response()->json(['data' => [
            'id' => $id,
            'phone' => $draft->phone,
            'expires_in' => (int) config('booking.otp.ttl_minutes') * 60,
        ]], 202);
    }

    public function confirm(ConfirmBookingOtpRequest $request, string $bookingRequest, ConfirmBookingOtpAction $confirm): JsonResponse
    {
        $appointment = $confirm->execute($bookingRequest, $request->string('code')->toString());

        return (new AppointmentResource($appointment))
            ->additional(['meta' => ['cancel_url' => $appointment->cancelUrl()]])
            ->response()
            ->setStatusCode(201);
    }
}
