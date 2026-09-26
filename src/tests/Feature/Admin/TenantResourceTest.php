<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\GalleryImage;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $shop;

    private Tenant $rival;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->shop = Tenant::factory()->create(['name' => 'Own Shop']);
        $this->rival = Tenant::factory()->create(['name' => 'Rival Shop']);
    }

    public function test_a_tenant_admin_only_sees_and_edits_their_own_shop(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords([$this->shop])
            ->assertCanNotSeeTableRecords([$this->rival]);

        $this->get(TenantResource::getUrl('edit', ['record' => $this->shop]))->assertOk();
        $this->get(TenantResource::getUrl('edit', ['record' => $this->rival]))->assertNotFound();
        $this->get(TenantResource::getUrl('create'))->assertForbidden();
    }

    public function test_a_tenant_admin_can_publish_their_landing_page_profile(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());
        $originalSlug = $this->shop->slug;

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->assertFormFieldIsDisabled('slug')
            ->fillForm([
                'currency' => 'RSD',
                'tagline' => 'Fades with a view.',
                'about_text' => "Para one.\n\nPara two.",
                'address' => "Kneza Mihaila 12\n11000 Belgrade",
                'phone' => '+381 11 328 4471',
                'social_instagram' => 'https://www.instagram.com/ownshop',
                'social_facebook' => null,
                'hero_image_path' => UploadedFile::fake()->image('hero.png', 1600, 900),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->shop->refresh();
        $this->assertSame($originalSlug, $this->shop->slug);
        $this->assertSame('RSD', $this->shop->currency);
        $this->assertSame('Fades with a view.', $this->shop->tagline);
        $this->assertStringStartsWith(Tenant::HERO_DIRECTORY.'/', $this->shop->hero_image_path);
        Storage::disk('public')->assertExists($this->shop->hero_image_path);

        $this->get("/book/{$this->shop->slug}")
            ->assertOk()
            ->assertSee('Fades with a view.')
            ->assertSee(Storage::disk('public')->url($this->shop->hero_image_path));
    }

    public function test_social_links_must_be_https_urls(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->fillForm([
                'social_instagram' => 'javascript:alert(1)',
                'social_facebook' => 'http://facebook.com/ownshop',
            ])
            ->call('save')
            ->assertHasFormErrors(['social_instagram', 'social_facebook']);
    }

    public function test_a_super_admin_can_open_a_shop_with_a_generated_slug(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(CreateTenant::class)
            ->fillForm(['name' => 'Kragujevac Cuts', 'timezone' => 'Europe/Belgrade', 'currency' => 'RSD'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tenants', ['name' => 'Kragujevac Cuts', 'slug' => 'kragujevac-cuts', 'currency' => 'RSD']);
    }

    public function test_the_timezone_select_ships_every_zone_with_the_page(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->assertFormFieldExists('timezone', function (Select $field): bool {
                return ! $field->hasDynamicOptions()
                    && $field->getOptions()['Europe/Belgrade'] === 'Europe/Belgrade'
                    && $field->getOptionsLimit() >= count($field->getOptions());
            })
            ->fillForm(['timezone' => 'America/New_York'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('America/New_York', $this->shop->refresh()->timezone);
    }

    public function test_a_shop_admin_curates_their_gallery_and_removed_photos_leave_the_disk(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());
        $stale = GalleryImage::factory()->for($this->shop)->create(['path' => GalleryImage::DIRECTORY.'/stale.jpg']);
        Storage::disk('public')->put($stale->path, 'jpg');

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->set('data.galleryImages', [
                'new-1' => ['path' => [], 'caption' => 'Skin fade'],
                'new-2' => ['path' => [], 'caption' => 'Beard sculpt'],
            ])
            ->set('data.galleryImages.new-1.path.upload', UploadedFile::fake()->image('fade.png', 1200, 1200))
            ->set('data.galleryImages.new-2.path.upload', UploadedFile::fake()->image('beard.png', 1200, 1200))
            ->call('save')
            ->assertHasNoFormErrors();

        $images = $this->shop->galleryImages()->get();
        $this->assertSame(['Skin fade', 'Beard sculpt'], $images->pluck('caption')->all());
        $this->assertSame([1, 2], $images->pluck('sort_order')->all());
        $images->each(fn (GalleryImage $image) => Storage::disk('public')->assertExists($image->path));
        $this->assertStringStartsWith(GalleryImage::DIRECTORY.'/', $images->first()->path);

        $this->assertModelMissing($stale);
        Storage::disk('public')->assertMissing($stale->path);
    }

    public function test_the_gallery_is_capped_and_only_takes_images(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        // Required photo, so each item's file slot is enough to trip the cap without uploading 13 files.
        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->set('data.galleryImages', collect(range(1, 13))->mapWithKeys(fn (int $i): array => [
                "new-{$i}" => ['path' => [], 'caption' => null],
            ])->all())
            ->call('save')
            ->assertHasFormErrors(['galleryImages']);

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->set('data.galleryImages', ['new-1' => ['path' => [], 'caption' => null]])
            ->set('data.galleryImages.new-1.path.upload', UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasFormErrors(['galleryImages.new-1.path']);

        $this->assertSame(0, $this->shop->galleryImages()->count());
    }

    public function test_a_shop_admin_can_publish_their_own_faq(): void
    {
        $this->actingAs(User::factory()->tenantAdmin($this->shop)->create());

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->set('data.faqs', [
                'a' => ['question' => 'Do you take walk-ins?', 'answer' => 'Weekdays before noon.'],
                'b' => ['question' => '', 'answer' => 'Orphan answer'],
            ])
            ->call('save')
            ->assertHasFormErrors(['faqs.b.question']);

        Livewire::test(EditTenant::class, ['record' => $this->shop->getRouteKey()])
            ->set('data.faqs', ['a' => ['question' => 'Do you take walk-ins?', 'answer' => 'Weekdays before noon.']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([['question' => 'Do you take walk-ins?', 'answer' => 'Weekdays before noon.']], $this->shop->refresh()->faqItems());
    }

    public function test_the_shop_list_counts_staff_and_services(): void
    {
        StaffMember::factory()->count(2)->for($this->shop)->create();
        Service::factory()->count(3)->for($this->shop)->create();

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ListTenants::class)
            ->assertTableColumnStateSet('staff_members_count', 2, $this->shop->getKey())
            ->assertTableColumnStateSet('services_count', 3, $this->shop->getKey())
            ->assertTableColumnStateSet('services_count', 0, $this->rival->getKey());
    }
}
