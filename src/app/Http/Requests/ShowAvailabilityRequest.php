<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\StaffMember;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint.
    }

    public function rules(): array
    {
        // "Today" is the shop's today, not the server's: near midnight they differ.
        $today = $this->tenant()->localNow()->startOfDay();

        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->addDays((int) config('booking.max_advance_days'))->toDateString(),
            ],
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('is_active', true)],
        ];
    }

    public function tenant(): Tenant
    {
        return $this->route('tenant');
    }

    /**
     * The requested date as a calendar day in the tenant's timezone.
     */
    public function day(): CarbonImmutable
    {
        return $this->tenant()->localTime('Y-m-d', $this->validated('date'))->startOfDay();
    }

    public function staffMember(): ?StaffMember
    {
        return $this->filled('staff_member_id')
            ? StaffMember::findOrFail($this->integer('staff_member_id'))
            : null;
    }
}
