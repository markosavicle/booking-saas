<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Widgets\StatsOverview;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DashboardStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $shop;

    private Tenant $rival;

    private Service $shopCut;

    private Service $rivalCut;

    protected function setUp(): void
    {
        parent::setUp();

        // 23:30 UTC on the 28th is already 01:30 on the 29th in Belgrade (UTC+2).
        $this->travelTo(CarbonImmutable::parse('2026-09-28 23:30', 'UTC'));

        $this->shop = Tenant::factory()->create(['timezone' => 'Europe/Belgrade', 'currency' => 'EUR']);
        $this->rival = Tenant::factory()->create(['timezone' => 'UTC', 'currency' => 'RSD']);
        $this->shopCut = Service::factory()->for($this->shop)->create(['price' => 25]);
        $this->rivalCut = Service::factory()->for($this->rival)->create(['price' => 1500]);
    }

    public function test_a_shop_admin_only_counts_their_own_shop(): void
    {
        $regular = User::factory()->create();
        $this->book($this->shopCut, '2026-09-28 22:30', $regular);  // 00:30 local: today, already past
        $this->book($this->shopCut, '2026-09-29 08:00', $regular);  // today, upcoming
        $this->book($this->shopCut, '2026-09-30 08:00');            // upcoming, not today
        $this->book($this->shopCut, '2026-09-28 21:00');            // 23:00 local on the 28th: yesterday
        $this->book($this->shopCut, '2026-09-29 09:00', status: 'canceled');
        $this->book($this->rivalCut, '2026-09-28 23:45', $regular); // rival's today and future

        $stats = app(DashboardStatsService::class)->for(User::factory()->tenantAdmin($this->shop)->create());

        $this->assertSame(2, $stats->appointmentsToday);
        $this->assertSame(['EUR' => '50.00'], $stats->upcomingRevenue);
        $this->assertSame(4, $stats->customers, 'Regular counts once; the rival booking and its customer are excluded.');
    }

    public function test_a_super_admin_sees_the_platform_with_revenue_split_by_currency(): void
    {
        $this->book($this->shopCut, '2026-09-29 08:00');
        $this->book($this->rivalCut, '2026-09-28 23:45');
        $this->book($this->rivalCut, '2026-09-29 00:15'); // tomorrow in UTC

        $stats = app(DashboardStatsService::class)->for(User::factory()->superAdmin()->create());

        $this->assertSame(2, $stats->appointmentsToday);
        $this->assertSame(['EUR' => '25.00', 'RSD' => '3000.00'], $stats->upcomingRevenue);
        $this->assertSame(3, $stats->customers);
    }

    public function test_a_shop_admin_without_a_shop_sees_nothing(): void
    {
        $this->book($this->shopCut, '2026-09-29 08:00');
        $orphan = User::factory()->tenantAdmin($this->shop)->create();
        $orphan->forceFill(['tenant_id' => null])->save();

        $stats = app(DashboardStatsService::class)->for($orphan);

        $this->assertSame([0, [], 0], [$stats->appointmentsToday, $stats->upcomingRevenue, $stats->customers]);
    }

    public function test_the_dashboard_widget_shows_the_shop_admins_figures_only(): void
    {
        $this->book($this->shopCut, '2026-09-29 08:00');
        $this->book($this->rivalCut, '2026-09-29 08:00');

        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::withoutLazyLoading()->test(StatsOverview::class)
            ->assertSeeInOrder(['Appointments today', '1', 'Upcoming revenue', '€25.00', 'Total customers', '1'])
            ->assertDontSee('RSD');

        $this->get('/admin')->assertOk()->assertSeeLivewire(StatsOverview::class);
    }

    public function test_the_widget_labels_each_currency_for_a_super_admin(): void
    {
        $this->book($this->shopCut, '2026-09-29 08:00');
        $this->book($this->rivalCut, '2026-09-29 08:00');

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::withoutLazyLoading()->test(StatsOverview::class)
            ->assertSee('Upcoming revenue (EUR)')
            ->assertSee('Upcoming revenue (RSD)');
    }

    private function book(Service $service, string $utcStart, ?User $customer = null, string $status = 'confirmed'): Appointment
    {
        return Appointment::factory()
            ->forService($service)
            ->for($customer ?? User::factory(), 'user')
            ->at(CarbonImmutable::parse($utcStart, 'UTC'))
            ->create(['status' => $status]);
    }
}
