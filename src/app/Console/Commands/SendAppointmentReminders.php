<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingConfirmationJob;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';
    protected $description = 'Send 24-hour email reminders for upcoming appointments';

    public function handle(): void
    {
        $targetTime = now()->addDays(1)->startOfMinute();

        // Find all appointments exactly 24 hours from now
        $appointments = Appointment::with(['user', 'service', 'tenant'])
            ->where('start_time', '>=', $targetTime)
            ->where('start_time', '<', $targetTime->copy()->addMinute())
            ->get();

        $this->info("Found {$appointments->count()} appointments requiring reminders.");

        foreach ($appointments as $appointment) {
            // Dispatch our refactored job that accepts the model
            SendBookingConfirmationJob::dispatch($appointment);
            $this->info("Dispatched reminder for Appointment ID: {$appointment->id}");
        }
    }
}
