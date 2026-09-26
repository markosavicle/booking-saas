<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
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
}
