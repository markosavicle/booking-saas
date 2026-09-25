<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

final class AppointmentPolicy
{
    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id
            || $user->isSuperAdmin()
            || ($user->isTenantAdmin() && $user->tenant_id === $appointment->tenant_id);
    }
}
