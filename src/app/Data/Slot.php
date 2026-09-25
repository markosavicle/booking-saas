<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class Slot
{
    /**
     * @param  list<int>  $staffIds  Staff members free for the whole slot.
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $staffIds,
    ) {}

    public function isAvailable(): bool
    {
        return $this->staffIds !== [];
    }
}
