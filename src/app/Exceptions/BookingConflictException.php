<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The request was valid but conflicts with the current state of the calendar.
 */
final class BookingConflictException extends RuntimeException
{
    public static function slotTaken(): self
    {
        return new self('The selected time slot is no longer available.');
    }

    public static function alreadyCanceled(): self
    {
        return new self('This appointment has already been canceled.');
    }

    public static function alreadyStarted(): self
    {
        return new self('Appointments that have already started cannot be canceled.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
