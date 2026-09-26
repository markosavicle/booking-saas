<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DashboardStats;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin dashboard figures. Scoped explicitly to the viewer's shop rather than trusting
 * TenantScope alone, so the numbers stay correct outside an authenticated request too.
 */
final class DashboardStatsService
{
    public function for(User $user): DashboardStats
    {
        // Fail closed: only a super admin may see platform-wide numbers.
        if (! $user->isSuperAdmin() && $user->tenant_id === null) {
            return new DashboardStats(appointmentsToday: 0, upcomingRevenue: [], customers: 0);
        }

        $tenantId = $user->isSuperAdmin() ? null : $user->tenant_id;

        return new DashboardStats(
            appointmentsToday: $this->appointmentsToday($tenantId),
            upcomingRevenue: $this->upcomingRevenue($tenantId),
            customers: $this->appointments($tenantId)->distinct()->count('appointments.user_id'),
        );
    }

    /**
     * "Today" is each shop's own calendar day, so shops are grouped by timezone into one query.
     */
    private function appointmentsToday(?int $tenantId): int
    {
        $shopsByTimezone = Tenant::query()
            ->when($tenantId !== null, fn (Builder $query) => $query->whereKey($tenantId))
            ->get(['id', 'timezone'])
            ->groupBy('timezone');

        if ($shopsByTimezone->isEmpty()) {
            return 0;
        }

        return $this->appointments($tenantId)
            ->blocking()
            ->where(function (Builder $query) use ($shopsByTimezone): void {
                foreach ($shopsByTimezone as $timezone => $shops) {
                    $start = $shops->first()->localNow()->startOfDay();

                    $query->orWhere(fn (Builder $day) => $day
                        ->whereIn('appointments.tenant_id', $shops->modelKeys())
                        ->where('appointments.start_time', '>=', $start->utc())
                        ->where('appointments.start_time', '<', $start->addDay()->utc()));
                }
            })
            ->count();
    }

    /**
     * @return array<string, string>
     */
    private function upcomingRevenue(?int $tenantId): array
    {
        return $this->appointments($tenantId)
            ->blocking()
            ->where('appointments.start_time', '>=', now())
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->join('tenants', 'tenants.id', '=', 'appointments.tenant_id')
            ->groupBy('tenants.currency')
            ->orderBy('tenants.currency')
            ->selectRaw('tenants.currency, SUM(services.price) as total')
            ->toBase()
            ->pluck('total', 'currency')
            ->map(fn ($total): string => number_format((float) $total, 2, '.', ''))
            ->all();
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointments(?int $tenantId): Builder
    {
        return Appointment::query()
            ->when($tenantId !== null, fn (Builder $query) => $query->where('appointments.tenant_id', $tenantId));
    }
}
