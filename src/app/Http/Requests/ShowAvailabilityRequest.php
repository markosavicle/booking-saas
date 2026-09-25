<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\StaffMember;
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
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                'before_or_equal:'.today()->addDays((int) config('booking.max_advance_days'))->toDateString(),
            ],
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('is_active', true)],
        ];
    }

    public function day(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->validated('date'))->startOfDay();
    }

    public function staffMember(): ?StaffMember
    {
        return $this->filled('staff_member_id')
            ? StaffMember::findOrFail($this->integer('staff_member_id'))
            : null;
    }
}
