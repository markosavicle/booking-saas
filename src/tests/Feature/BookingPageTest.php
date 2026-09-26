<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StaffMember;
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

    public function test_the_page_without_a_shop_is_a_neutral_shell_over_the_shop_picker(): void
    {
        $this->get('/book')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('bookingWidget(null)', false)
            ->assertDontSee('Opening hours')
            ->assertDontSee('id="team"', false);
    }

    public function test_unknown_shops_return_404(): void
    {
        $this->get('/book/nope')->assertNotFound();
    }
}
