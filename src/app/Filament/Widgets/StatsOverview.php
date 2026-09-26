<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected static ?string $pollingInterval = '60s';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $stats = app(DashboardStatsService::class)->for($user);

        $revenue = $stats->upcomingRevenue === []
            ? [Stat::make('Upcoming revenue', Number::currency(0, in: $user->tenant?->currency ?? 'EUR'))]
            : array_map(
                fn (string $currency, string $total): Stat => Stat::make(
                    count($stats->upcomingRevenue) > 1 ? "Upcoming revenue ({$currency})" : 'Upcoming revenue',
                    Number::currency((float) $total, in: $currency),
                ),
                array_keys($stats->upcomingRevenue),
                $stats->upcomingRevenue,
            );

        return [
            Stat::make('Appointments today', $stats->appointmentsToday)
                ->description('Pending and confirmed')
                ->icon('heroicon-o-calendar-days'),
            ...array_map(fn (Stat $stat): Stat => $stat
                ->description('Booked services from now on')
                ->icon('heroicon-o-banknotes'), $revenue),
            Stat::make('Total customers', $stats->customers)
                ->description('Booked at least once')
                ->icon('heroicon-o-users'),
        ];
    }
}
