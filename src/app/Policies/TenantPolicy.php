<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Super admins run the platform; tenant admins may only edit their own shop's profile.
 */
final class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isTenantAdmin();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin() || ($user->isTenantAdmin() && $user->tenant_id === $tenant->id);
    }

    // Deleting a shop cascades into bookings and customer history; not exposed in the panel.
    public function delete(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
