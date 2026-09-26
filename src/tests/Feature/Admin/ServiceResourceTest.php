<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prices_are_listed_in_each_shops_own_currency(): void
    {
        $dinar = Service::factory()->for(Tenant::factory()->create(['currency' => 'RSD']))->create(['price' => 2500]);
        $euro = Service::factory()->for(Tenant::factory()->create(['currency' => 'EUR']))->create(['price' => 25]);

        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(ListServices::class)
            ->assertTableColumnFormattedStateSet('price', Number::currency(2500, in: 'RSD'), $dinar)
            ->assertTableColumnFormattedStateSet('price', Number::currency(25, in: 'EUR'), $euro)
            ->assertDontSeeHtml('$2,500');
    }

    public function test_the_price_prefix_follows_the_selected_shop(): void
    {
        $shop = Tenant::factory()->create(['currency' => 'RSD']);
        $service = Service::factory()->for($shop)->create();

        $this->actingAs(User::factory()->tenantAdmin($shop)->create());

        $hasPrefix = fn (string $currency) => fn (TextInput $field): bool => $field->getPrefixLabel() === $currency;

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->assertFormFieldExists('price', $hasPrefix('RSD'));

        // A shop admin's new service starts on their own shop, so the prefix is right before they touch anything.
        Livewire::test(CreateService::class)
            ->assertFormSet(['tenant_id' => $shop->id])
            ->assertFormFieldExists('price', $hasPrefix('RSD'));
    }
}
