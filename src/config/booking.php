<?php

declare(strict_types=1);

return [
    // Granularity of bookable start times, counted from the tenant's opening time.
    'slot_interval_minutes' => (int) env('BOOKING_SLOT_INTERVAL_MINUTES', 30),

    // How far ahead customers may book.
    'max_advance_days' => (int) env('BOOKING_MAX_ADVANCE_DAYS', 60),

    // Reminders go out this many hours before an appointment. Bookings made inside
    // this window are marked as reminded, since the confirmation already covers it.
    'reminder_lead_hours' => (int) env('BOOKING_REMINDER_LEAD_HOURS', 24),
];
