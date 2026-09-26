<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_profile_text_is_split_into_display_lines(): void
    {
        $tenant = Tenant::factory()->make([
            'address' => "  Kneza Mihaila 12 \r\n\r\n11000 Belgrade  ",
            'about_text' => "First paragraph\nstill first.\n\n  \nSecond paragraph.",
            'phone' => '+381 (11) 328-4471',
        ]);

        $this->assertSame(['Kneza Mihaila 12', '11000 Belgrade'], $tenant->addressLines());
        $this->assertSame(["First paragraph\nstill first.", 'Second paragraph.'], $tenant->aboutParagraphs());
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=Kneza%20Mihaila%2012%2C%2011000%20Belgrade', $tenant->mapsUrl());
        $this->assertSame('tel:+381113284471', $tenant->phoneHref());
    }

    public function test_an_empty_profile_yields_nothing_to_render(): void
    {
        $tenant = Tenant::factory()->make();

        $this->assertSame([], $tenant->addressLines());
        $this->assertSame([], $tenant->aboutParagraphs());
        $this->assertNull($tenant->mapsUrl());
        $this->assertNull($tenant->phoneHref());
        $this->assertSame('EUR', Tenant::factory()->create()->refresh()->currency);
    }

    public function test_the_hero_falls_back_to_the_bundled_stock_photo(): void
    {
        $this->assertSame(asset(Tenant::DEFAULT_HERO_IMAGE), Tenant::factory()->make()->heroImageUrl());
        $this->assertFileExists(public_path(Tenant::DEFAULT_HERO_IMAGE));

        $tenant = Tenant::factory()->make(['hero_image_path' => 'tenants/heroes/shop.jpg']);
        $this->assertSame(Storage::disk('public')->url('tenants/heroes/shop.jpg'), $tenant->heroImageUrl());
    }

    public function test_replaced_and_orphaned_hero_images_are_deleted(): void
    {
        $disk = Storage::disk('public');
        $disk->put('tenants/heroes/old.jpg', 'old');
        $disk->put('tenants/heroes/new.jpg', 'new');
        $tenant = Tenant::factory()->create(['hero_image_path' => 'tenants/heroes/old.jpg']);

        $tenant->update(['tagline' => 'Unrelated change']);
        $disk->assertExists('tenants/heroes/old.jpg');

        $tenant->update(['hero_image_path' => 'tenants/heroes/new.jpg']);
        $disk->assertMissing('tenants/heroes/old.jpg');

        $tenant->delete();
        $disk->assertMissing('tenants/heroes/new.jpg');
    }
}
