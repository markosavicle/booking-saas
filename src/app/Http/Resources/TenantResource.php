<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BusinessHour;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'business_hours' => $this->whenLoaded('businessHours', fn () => $this->businessHours
                ->sortBy('day_of_week')
                ->map(fn (BusinessHour $hours): array => [
                    'day_of_week' => $hours->day_of_week,
                    'opens_at' => substr($hours->opens_at, 0, 5),
                    'closes_at' => substr($hours->closes_at, 0, 5),
                ])->values()),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'staff_members' => StaffMemberResource::collection($this->whenLoaded('staffMembers')),
        ];
    }
}
