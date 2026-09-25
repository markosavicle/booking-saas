<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Booking\GetAvailableSlotsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowAvailabilityRequest;
use App\Http\Resources\SlotResource;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AvailabilityController extends Controller
{
    public function show(
        ShowAvailabilityRequest $request,
        Tenant $tenant,
        Service $service,
        GetAvailableSlotsAction $availableSlots,
    ): AnonymousResourceCollection {
        abort_unless($service->is_active, 404);

        $service->setRelation('tenant', $tenant->load('businessHours'));

        return SlotResource::collection(
            $availableSlots->execute($service, $request->day(), $request->staffMember()),
        );
    }
}
