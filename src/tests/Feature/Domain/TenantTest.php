<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\BusinessHour;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_name(): void
    {
        $tenant = Tenant::create(['name' => 'Niš Classic Barbers']);

        $this->assertSame('nis-classic-barbers', $tenant->slug);
    }

    public function test_slug_collisions_get_a_numeric_suffix(): void
    {
        Tenant::create(['name' => 'Fade Studio']);
        $second = Tenant::create(['name' => 'Fade Studio']);
        $third = Tenant::create(['name' => 'Fade Studio']);

        $this->assertSame('fade-studio-2', $second->slug);
        $this->assertSame('fade-studio-3', $third->slug);
    }

    public function test_explicit_slug_is_preserved(): void
    {
        $tenant = Tenant::create(['name' => 'Fade Studio', 'slug' => 'custom-slug']);

        $this->assertSame('custom-slug', $tenant->slug);
    }

    public function test_hours_for_returns_the_matching_weekday(): void
    {
        $tenant = Tenant::factory()->withStandardHours('09:00', '21:00')->create();
        $monday = CarbonImmutable::parse('2026-09-28');

        $hours = $tenant->hoursFor($monday);

        $this->assertInstanceOf(BusinessHour::class, $hours);
        $this->assertSame('2026-09-28 09:00:00', $hours->opensOn($monday)->toDateTimeString());
        $this->assertSame('2026-09-28 21:00:00', $hours->closesOn($monday)->toDateTimeString());
    }

    public function test_hours_for_returns_null_when_closed(): void
    {
        $tenant = Tenant::factory()->withStandardHours()->create();

        $this->assertNull($tenant->hoursFor(CarbonImmutable::parse('2026-09-27'))); // Sunday
    }

    public function test_hours_for_uses_eager_loaded_relation_without_querying(): void
    {
        Tenant::factory()->withStandardHours()->create();
        $tenant = Tenant::with('businessHours')->firstOrFail();

        DB::enableQueryLog();
        $tenant->hoursFor(CarbonImmutable::parse('2026-09-28'));

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_a_tenant_cannot_have_two_rows_for_the_same_weekday(): void
    {
        $tenant = Tenant::factory()->create();
        BusinessHour::factory()->for($tenant)->create(['day_of_week' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        BusinessHour::factory()->for($tenant)->create(['day_of_week' => 1]);
    }
}
