<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GalleryImage;
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
            ->filter(fn (string $sql): bool => str_starts_with($sql, 'select * from "staff_members"') || str_contains($sql, 'from "tenants" where "tenants"."id" in'));
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

    public function test_the_directory_links_every_shop_to_its_own_page(): void
    {
        $rival = Tenant::factory()->create([
            'name' => 'Novi Sad Fade Studio',
            'tagline' => 'Fades for the Danube crowd.',
            'address' => "Zmaj Jovina 8\n21000 Novi Sad",
        ]);
        StaffMember::factory()->for($rival)->count(2)->create();
        StaffMember::factory()->for($rival)->inactive()->create();
        DB::enableQueryLog();

        $response = $this->get('/book')
            ->assertOk()
            ->assertSee('id="shops"', false)
            ->assertSeeInOrder(['id="shops"', '21000 Novi Sad', $rival->name, 'Fades for the Danube crowd.', '2 barbers', 'id="book"'], false)
            ->assertSee('href="'.route('booking', $rival).'"', false)
            ->assertSee('href="'.route('booking', $this->tenant).'"', false);

        $directoryQueries = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $sql): bool => str_contains($sql, 'as "staff_members_count"'));
        $this->assertCount(1, $directoryQueries, 'Staff counts must come from one withCount query, not one per shop.');

        // A shop's own page is about that shop only.
        $this->get("/book/{$rival->slug}")->assertOk()->assertDontSee('id="shops"', false);
    }

    public function test_a_shop_page_shows_its_gallery_in_order_and_hides_it_when_empty(): void
    {
        $this->get("/book/{$this->tenant->slug}")->assertOk()->assertDontSee('id="gallery"', false);

        $second = GalleryImage::factory()->for($this->tenant)->create(['caption' => 'The shop floor', 'sort_order' => 2]);
        $first = GalleryImage::factory()->for($this->tenant)->create(['caption' => 'Skin fade', 'sort_order' => 1]);
        GalleryImage::factory()->create(['caption' => 'Someone else\'s cut']);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('href="#gallery"', false)
            ->assertSeeInOrder(['id="gallery"', $first->url(), 'alt="Skin fade"', $second->url(), 'alt="The shop floor"', 'id="team"'], false)
            ->assertDontSee('Someone else&#039;s cut', false);
    }

    public function test_the_faq_shows_platform_answers_until_a_shop_writes_its_own(): void
    {
        config(['booking.reminder_lead_hours' => 12]);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('id="faq"', false)
            ->assertSee('How do I cancel or reschedule?')
            ->assertSee('about 12 hours before')
            ->assertSee('"@type":"FAQPage"', false);

        $this->get('/book')->assertOk()->assertSee('How do I cancel or reschedule?');

        $this->tenant->update(['faqs' => [
            ['question' => 'Is there parking?', 'answer' => 'Free behind the shop.'],
            ['question' => 'Half-filled', 'answer' => ''],
        ]]);

        $this->get("/book/{$this->tenant->slug}")
            ->assertOk()
            ->assertSee('Is there parking?')
            ->assertSee('Free behind the shop.')
            ->assertDontSee('Half-filled')
            ->assertDontSee('How do I cancel or reschedule?');
    }

    public function test_faq_content_is_escaped_including_inside_the_structured_data(): void
    {
        $this->tenant->update(['faqs' => [
            ['question' => '<b>Bold?</b>', 'answer' => '</script><script>alert(1)</script>'],
        ]]);

        $html = $this->get("/book/{$this->tenant->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>Bold?</b>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_a_shop_admin_browsing_a_rival_shop_sees_its_whole_page(): void
    {
        $rival = Tenant::factory()->create();
        StaffMember::factory()->for($rival)->create(['name' => 'Rival Barber']);
        GalleryImage::factory()->for($rival)->create(['caption' => 'Rival fade']);

        $this->actingAs(User::factory()->tenantAdmin($this->tenant)->create())
            ->get("/book/{$rival->slug}")
            ->assertOk()
            ->assertSee('Rival Barber')
            ->assertSee('Rival fade');
    }
}
