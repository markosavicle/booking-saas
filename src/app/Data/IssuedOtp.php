<?php

declare(strict_types=1);

namespace App\Data;

use SensitiveParameter;

/**
 * A freshly issued booking code. The plain code exists only here and in the SMS;
 * the cache keeps a keyed hash. Callers may reveal it only in demo mode.
 */
final readonly class IssuedOtp
{
    public function __construct(
        public string $id,
        #[SensitiveParameter] public string $code,
    ) {}
}
