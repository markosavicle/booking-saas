<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Possession of the SMS code is the authorisation.
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
