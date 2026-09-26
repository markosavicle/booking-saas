<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNumber
{
    /** E.164: "+" followed by 7–15 digits, no leading zero. */
    public const string E164_PATTERN = '/^\+[1-9]\d{6,14}$/';

    /**
     * Strips formatting people type ("+381 60 123-4567", "00381…") so the same number
     * always yields the same value, which rate limiting and identity both rely on.
     */
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/[\s\-().\/]/', '', (string) $value) ?? '';

        return str_starts_with($digits, '00') ? '+'.substr($digits, 2) : $digits;
    }
}
