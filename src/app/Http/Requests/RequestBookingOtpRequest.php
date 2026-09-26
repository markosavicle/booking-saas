<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BookingDraft;
use App\Support\PhoneNumber;

/**
 * A guest's booking details; the slot rules are inherited from StoreBookingRequest.
 */
class RequestBookingOtpRequest extends StoreBookingRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize($this->input('phone')),
            'email' => filled($this->input('email')) ? mb_strtolower(trim((string) $this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::E164_PATTERN],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter the phone number with its country code, e.g. +381 60 1234567.'];
    }

    public function draft(): BookingDraft
    {
        return new BookingDraft(
            serviceId: $this->service()->id,
            staffMemberId: $this->staffMember()?->id,
            start: $this->startTime(),
            name: trim((string) $this->input('name')),
            phone: (string) $this->input('phone'),
            email: $this->input('email'),
        );
    }
}
