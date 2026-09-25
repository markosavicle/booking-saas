<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Jobs\SendBookingConfirmationJob;
use App\Models\Service;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookingService) {}

    /**
     * Get available slots for a given service and date.
     */
    public function availableSlots(Request $request, Service $service): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $slots = $this->bookingService->getAvailableSlots($service, $request->query('date'));

        return response()->json(['data' => $slots]);
    }

    /**
     * Store a new appointment booking.
     */
public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $serviceId = $request->validated('service_id');

            // 1. Save to database via your existing service
            $appointment = $this->bookingService->createBooking(
                tenantId: $user->tenant_id,
                serviceId: $serviceId,
                userId: $user->id,
                startTime: $request->validated('start_time')
            );

            // 2. We can load the service name from the relationships if we need it, 
            // or pass a default to the email job if we are bypassing a complex query.
            // For this phase, we'll let the worker job retrieve it if needed, or pass it directly.
            $serviceName = Service::withoutGlobalScopes()->find($serviceId)?->name ?? 'Booked Service';

            // 3. Dispatch the email job to Redis
            SendBookingConfirmationJob::dispatch(
                $user->email,
                $user->name,
                $user->tenant->name ?? 'Our Business',
                $serviceName,
                $request->validated('start_time')
            );

            return response()->json([
                'message' => 'Booking confirmed successfully.',
                'data' => $appointment,
                'status' => 'email_queued'
            ], 201);

        } catch (Exception $e) {
            $status = $e->getCode() === 409 ? 409 : 400;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
