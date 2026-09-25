<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * Times are wall-clock at the shop; callers must eager-load `tenant`.
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->tenant->timezone;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_time' => $this->start_time->setTimezone($timezone)->format(StoreBookingRequest::TIME_FORMAT),
            'end_time' => $this->end_time->setTimezone($timezone)->format(StoreBookingRequest::TIME_FORMAT),
            'timezone' => $timezone,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'slug' => $this->tenant->slug,
            ]),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'staff_member' => new StaffMemberResource($this->whenLoaded('staffMember')),
        ];
    }
}
