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

    // Public demo: booking codes are shown on screen instead of texted, and no SMS is ever sent
    // (the log driver is forced). Banners on every page say so. Never enable for a real shop.
    'demo_mode' => (bool) env('BOOKING_DEMO_MODE', false),

    // SMS codes that confirm a guest booking.
    'otp' => [
        'ttl_minutes' => (int) env('BOOKING_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('BOOKING_OTP_MAX_ATTEMPTS', 5),
    ],
];
