<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public const string TIME_FORMAT = 'Y-m-d H:i';

    public function authorize(): bool
    {
        return true; // Any authenticated user may book; auth:sanctum guards the route.
    }

    public function rules(): array
    {
        $maxDate = today()->addDays((int) config('booking.max_advance_days'))->endOfDay();

        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('is_active', true)],
            'start_time' => [
                'required',
                'date_format:'.self::TIME_FORMAT,
                'after:now',
                'before_or_equal:'.$maxDate->toDateTimeString(),
            ],
        ];
    }

    public function service(): Service
    {
        return Service::with('tenant.businessHours')->findOrFail($this->integer('service_id'));
    }

    public function staffMember(): ?StaffMember
    {
        return $this->filled('staff_member_id')
            ? StaffMember::findOrFail($this->integer('staff_member_id'))
            : null;
    }

    public function startTime(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(self::TIME_FORMAT, $this->validated('start_time'))->startOfMinute();
    }
}
