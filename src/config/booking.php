<?php

declare(strict_types=1);

return [
    // Granularity of bookable start times, counted from the tenant's opening time.
    'slot_interval_minutes' => (int) env('BOOKING_SLOT_INTERVAL_MINUTES', 30),

    // How far ahead customers may book.
    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 60),
];
