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
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_time' => $this->start_time->format(StoreBookingRequest::TIME_FORMAT),
            'end_time' => $this->end_time->format(StoreBookingRequest::TIME_FORMAT),
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
