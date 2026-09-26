<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    /** Wall-clock format used by the API, always in the tenant's timezone. */
    public const string TIME_FORMAT = 'Y-m-d H:i';

    private ?Service $service = null;

    public function authorize(): bool
    {
        return true; // Guarded by auth:sanctum or, for guests, by the SMS code.
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'staff_member_id' => ['nullable', 'integer', Rule::exists('staff_members', 'id')->where('is_active', true)],
            // Range checks need the tenant's timezone, so they run in after().
            'start_time' => ['required', 'date_format:'.self::TIME_FORMAT],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['service_id', 'start_time'])) {
                    return;
                }

                $start = $this->startTime();
                $latest = $this->service()->tenant->localNow()
                    ->addDays((int) config('booking.max_advance_days'))
                    ->endOfDay();

                if ($start->isPast()) {
                    $validator->errors()->add('start_time', 'The start time must be in the future.');
                } elseif ($start->greaterThan($latest)) {
                    $validator->errors()->add('start_time', 'The start time is too far in advance.');
                }
            },
        ];
    }

    public function service(): Service
    {
        return $this->service ??= Service::with('tenant.businessHours')->findOrFail($this->integer('service_id'));
    }

    public function staffMember(): ?StaffMember
    {
        return $this->filled('staff_member_id')
            ? StaffMember::findOrFail($this->integer('staff_member_id'))
            : null;
    }

    /**
     * The requested start as a UTC instant; the input is wall-clock time at the shop.
     */
    public function startTime(): CarbonImmutable
    {
        return $this->service()->tenant
            ->localTime(self::TIME_FORMAT, (string) $this->input('start_time'))
            ->startOfMinute()
            ->utc();
    }
}
