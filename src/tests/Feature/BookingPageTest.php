<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\BookingTestCase;

class BookingPageTest extends BookingTestCase
{
    public function test_a_shops_landing_page_renders_its_profile_around_the_widget(): void
    {
        StaffMember::factory()->for($this->tenant)->inactive()->create(['name' => 'Retired Rick']);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee($this->tenant->name)
            ->assertSee("bookingWidget('{$this->tenant->slug}')", false)
            ->assertSeeInOrder(['Opening hours', 'Monday', '09:00 – 12:00', 'Sunday', 'Closed'])
            ->assertSeeInOrder(['id="team"', 'Anna', 'Ben'], false)
            ->assertDontSee('Retired Rick');
    }

    public function test_the_landing_page_shows_the_shops_own_profile(): void
    {
        Storage::fake('public');
        $this->tenant->update([
            'tagline' => 'Fades with a <view>.',
            'about_text' => "First paragraph.\n\nSecond paragraph.",
            'address' => "Kneza Mihaila 12\n11000 Belgrade",
            'phone' => '+381 11 328 4471',
            'social_instagram' => 'https://www.instagram.com/shop',
            'hero_image_path' => 'tenants/heroes/shop.jpg',
        ]);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('Fades with a &lt;view&gt;.', false)
            ->assertSeeInOrder(['<p>First paragraph.</p>', '<p>Second paragraph.</p>'], false)
            ->assertSeeInOrder(['Kneza Mihaila 12', '<br>', '11000 Belgrade'], false)
            ->assertSee($this->tenant->mapsUrl())
            ->assertSee('href="tel:+381113284471"', false)
            ->assertSee('href="https://www.instagram.com/shop"', false)
            ->assertDontSee('on Facebook')
            ->assertSee(Storage::disk('public')->url('tenants/heroes/shop.jpg'))
            ->assertDontSee(Tenant::DEFAULT_HERO_IMAGE);
    }

    public function test_a_shop_without_a_profile_falls_back_without_inventing_contact_details(): void
    {
        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee(asset(Tenant::DEFAULT_HERO_IMAGE))
            ->assertSee('Sharp cuts, hot towels')
            ->assertDontSee('Find us')
            ->assertDontSee('tel:', false)
            ->assertDontSee('Follow')
            ->assertDontSee('href="#"', false);
    }

    public function test_avatar_initials_handle_unicode_names(): void
    {
        $this->anna->update(['name' => 'Đorđe Petrović']);
        $this->ben->update(['name' => 'Šime']);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSeeInOrder(['id="team"', 'ĐP', 'Đorđe Petrović', 'ŠI', 'Šime'], false);
    }

    public function test_the_page_without_a_shop_is_a_neutral_shell_over_the_shop_picker(): void
    {
        $this->get('/book')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('bookingWidget(null)', false)
            ->assertDontSee('Opening hours');
    }

    public function test_the_page_without_a_shop_features_barbers_from_across_the_platform(): void
    {
        $rival = Tenant::factory()->create(['name' => 'Novi Sad Fade Studio']);
        StaffMember::factory()->for($rival)->create(['name' => 'Marko']);
        StaffMember::factory()->for($rival)->inactive()->create(['name' => 'Retired Rick']);

        $this->get('/book')
            ->assertOk()
            ->assertSee('id="team"', false)
            ->assertSee('Meet the barbers')
            ->assertSeeInOrder(['Marko', 'Barber', 'Novi Sad Fade Studio'])
            ->assertSee('href="'.route('booking', $rival).'"', false)
            ->assertSeeInOrder(['Anna', 'Barber', $this->tenant->name])
            ->assertSee('href="'.route('booking', $this->tenant).'"', false)
            ->assertDontSee('Retired Rick');
    }

    public function test_the_directory_is_capped_and_eager_loads_each_barbers_shop(): void
    {
        StaffMember::factory()->count(10)->create();
        DB::enableQueryLog();

        $response = $this->get('/book')->assertOk();

        $this->assertSame(6, substr_count($response->getContent(), 'after:absolute'));
        $staffQueries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql): bool => str_contains($sql, 'from "staff_members"') || str_contains($sql, 'from "tenants"'));
        $this->assertCount(2, $staffQueries, 'Expected one staff query plus one eager-loaded tenants query.');
    }

    public function test_a_signed_in_shop_admin_still_sees_the_whole_platform_directory(): void
    {
        $rival = Tenant::factory()->create(['name' => 'Rival Shop']);
        StaffMember::factory()->for($rival)->create(['name' => 'Marko']);

        $this->actingAs(User::factory()->tenantAdmin($this->tenant)->create())
            ->get('/book')
            ->assertOk()
            ->assertSee('Marko')
            ->assertSee('Anna');
    }

    public function test_unknown_shops_return_404(): void
    {
        $this->get('/book/nope')->assertNotFound();
    }
}
