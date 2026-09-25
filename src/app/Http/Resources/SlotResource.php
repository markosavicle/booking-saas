<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Data\Slot;
use App\Http\Requests\StoreBookingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'start_time' => $this->start->format(StoreBookingRequest::TIME_FORMAT),
            'end_time' => $this->end->format(StoreBookingRequest::TIME_FORMAT),
            'available' => $this->isAvailable(),
            'staff_member_ids' => $this->staffIds,
        ];
    }
}
