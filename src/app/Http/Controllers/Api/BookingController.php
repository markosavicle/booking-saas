<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $appointments = $request->user()->appointments()
            ->with(['tenant', 'service', 'staffMember'])
            ->orderByDesc('start_time')
            ->paginate();

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreBookingRequest $request, CreateBookingAction $createBooking): JsonResponse
    {
        $appointment = $createBooking->execute(
            $request->user(),
            $request->service(),
            $request->startTime(),
            $request->staffMember(),
        );

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function cancel(
        CancelBookingRequest $request,
        Appointment $appointment,
        CancelBookingAction $cancelBooking,
    ): AppointmentResource {
        return new AppointmentResource(
            $cancelBooking->execute($appointment)->load(['tenant', 'service', 'staffMember']),
        );
    }
}
