<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders
        {--hours= : Remind about confirmed appointments starting within this many hours (default: booking.reminder_lead_hours)}';

    protected $description = 'Queue reminders for upcoming confirmed appointments (each appointment is reminded at most once)';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('booking.reminder_lead_hours'));

        if ($hours < 1) {
            $this->error('--hours must be a positive integer.');

            return self::INVALID;
        }

        $now = now();
        $sent = 0;

        // A window rather than an exact minute, so a skipped or late scheduler run
        // catches up instead of silently dropping reminders.
        Appointment::query()
            ->withoutGlobalScopes()
            ->with(['user', 'tenant', 'service', 'staffMember'])
            ->where('status', AppointmentStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('start_time', '>', $now)
            ->where('start_time', '<=', $now->copy()->addHours($hours))
            ->lazyById(200)
            ->each(function (Appointment $appointment) use ($now, &$sent): void {
                if (! $this->claim($appointment, $now)) {
                    return;
                }

                $appointment->user->notify(new AppointmentReminder($appointment));
                $sent++;
            });

        $this->info("Queued {$sent} appointment reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Atomically mark the reminder as sent. Only the process whose UPDATE flips the
     * column from NULL wins, so overlapping runs can never double-send.
     */
    private function claim(Appointment $appointment, CarbonInterface $now): bool
    {
        return Appointment::query()
            ->withoutGlobalScopes()
            ->whereKey($appointment->getKey())
            ->whereNull('reminder_sent_at')
            ->update(['reminder_sent_at' => $now]) === 1;
    }
}
