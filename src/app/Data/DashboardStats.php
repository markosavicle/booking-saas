<?php

declare(strict_types=1);

namespace App\Data;

final readonly class DashboardStats
{
    /**
     * @param  array<string, string>  $upcomingRevenue  Totals keyed by ISO currency; shops never share one sum across currencies.
     */
    public function __construct(
        public int $appointmentsToday,
        public array $upcomingRevenue,
        public int $customers,
    ) {}
}
