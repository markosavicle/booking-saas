<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GalleryImage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Hero photos, galleries and FAQs for the three demo shops, matched by slug.
 *
 * Only fills gaps, so it is safe on a database with real edits:
 *   php artisan db:seed --class=DemoContentSeeder
 * A hero, FAQ or gallery a shop already has is never replaced. Photos are stock images
 * from Unsplash (Unsplash License) in database/seeders/images.
 */
final class DemoContentSeeder extends Seeder
{
    private const array SHOPS = [
        'belgrade-central-cuts' => [
            'hero' => 'barbershop-interior.jpg',
            'gallery' => [
                'barbershop-interior.jpg' => 'The shop floor: brick, leather and warm light',
                'gallery-straight-razor.jpg' => 'Straight-razor neckline and beard line-up',
                'gallery-mid-fade.jpg' => 'Mid fade with a hard part',
                'gallery-scissor-over-comb.jpg' => 'Scissor-over-comb on the sides',
                'gallery-tools.jpg' => 'Clippers, shears and pomade, ready for the next chair',
            ],
            'faqs' => [
                ['Do I need an account to book?', 'No. Pick a service, a barber (or "Anyone") and a time, enter your name and phone number, and confirm with the 6-digit code we text you.'],
                ["What's your cancellation policy?", 'Cancelling is free. Use the link in your confirmation SMS or e-mail any time before your appointment starts. We appreciate at least three hours\' notice so someone else can take the chair. To reschedule, cancel and book a new time.'],
                ['How can I pay?', 'At the end of your visit, in cash or by card (Visa, Mastercard, Maestro and contactless phone payments). Nothing is charged when you book online.'],
                ['Do you take walk-ins?', 'When a chair is free, yes. Weekday evenings and Saturdays usually fill up days ahead, so a booking is the only way to guarantee your time.'],
                ["What if I'm running late?", 'Give us a call. Up to ten minutes late we still do the full service; after that we may need to shorten it or move you to the next free slot.'],
                ['How long does a haircut take?', 'Every service shows its duration before you book. A standard cut is 30 minutes, including the consultation and a hot-towel finish.'],
                ["Do you cut children's hair?", 'Yes, from age five. Book a regular haircut; a parent or guardian stays in the shop.'],
                ['Where can I park?', 'Kneza Mihaila is pedestrian-only. The Obilićev venac public garage is a few minutes\' walk away.'],
            ],
        ],
        'novi-sad-fade-studio' => [
            'hero' => 'hero-novi-sad-shopfront.jpg',
            'gallery' => [
                'hero-novi-sad-shopfront.jpg' => 'The studio, lights on',
                'gallery-clipper-fade.jpg' => 'Skin fade, blended by clipper',
                'gallery-finishing-touches.jpg' => 'Finishing touches on a textured top',
                'gallery-blow-dry.jpg' => 'Blow-dry and style before you leave',
                'barbershop-fade.jpg' => 'Textured crop, finished',
            ],
            'faqs' => [
                ['Do you take walk-ins?', "Yes, whenever a chair is free; just come in and ask. Booking online guarantees your slot, so it's the safer bet on Fridays and Saturdays."],
                ['How often should I get a fade touched up?', 'A skin fade looks sharpest for about two to three weeks. Most regulars book every three weeks; a taper or longer cut lasts four to six.'],
                ['What should I bring?', 'A photo of the look you want helps more than any description. Come with clean, dry hair and no product if you can.'],
                ['Can I get my beard done at the same visit?', 'Yes. Choose a service that includes the beard, or book a beard service straight after your haircut with the same barber.'],
                ['How do I cancel?', 'Free, any time before your appointment starts, using the link in your confirmation SMS or e-mail. If plans change, please cancel rather than not showing up; someone else is usually waiting for the slot.'],
                ['How can I pay?', 'Cash or card at the end of your visit. Tips are appreciated, never expected.'],
                ["What if I'm running late?", 'Call or text us. We hold your chair for ten minutes; after that we may have to move you to the next free slot.'],
            ],
        ],
        'nis-classic-barbers' => [
            'hero' => 'hero-nis-vintage-chair.jpg',
            'gallery' => [
                'hero-nis-vintage-chair.jpg' => 'The original chair, still in use',
                'gallery-hot-towel-shave.jpg' => 'Hot towel shave',
                'gallery-scissor-trim.jpg' => 'A classic scissor cut',
                'gallery-classic-chair.jpg' => 'Chrome and leather, restored by hand',
            ],
            'faqs' => [
                ['Is it appointment only?', 'Mostly. We keep one chair free for walk-ins on Saturday mornings; every other slot is by appointment, which you can book right here.'],
                ['What happens during a hot towel shave?', "Allow 30 minutes. We soften the beard with hot towels and pre-shave oil, shave with a straight razor, and finish with a cold towel and balm. It works best with at least two days' growth."],
                ['What is your cancellation policy?', 'Cancelling is free: use the link in your confirmation SMS or e-mail any time before your appointment starts. To move your booking, cancel it and pick a new time.'],
                ['How can I pay?', 'Cash or card. Gift vouchers are sold in the shop.'],
                ["What if I'm running late?", 'Call us on the number on this page. We will always try to fit you in, but a late start may mean a shorter service.'],
                ['Can I choose my barber?', 'Yes, pick anyone on the team when you book, or choose "Anyone" to get the first free chair.'],
            ],
        ],
    ];

    public function run(): void
    {
        $tenants = Tenant::query()
            ->whereIn('slug', array_keys(self::SHOPS))
            ->withCount('galleryImages')
            ->get();

        foreach ($tenants as $tenant) {
            $content = self::SHOPS[$tenant->slug];
            $filled = [];

            if (blank($tenant->hero_image_path)) {
                $tenant->hero_image_path = $this->publish(Tenant::HERO_DIRECTORY, "{$tenant->slug}-{$content['hero']}", $content['hero']);
                $filled[] = 'hero';
            }

            if (blank($tenant->faqs)) {
                $tenant->faqs = array_map(fn (array $faq): array => ['question' => $faq[0], 'answer' => $faq[1]], $content['faqs']);
                $filled[] = 'FAQ';
            }

            $tenant->save();

            if ($tenant->gallery_images_count === 0) {
                $order = 0;
                foreach ($content['gallery'] as $file => $caption) {
                    $tenant->galleryImages()->create([
                        'path' => $this->publish(GalleryImage::DIRECTORY, "{$tenant->slug}-{$file}", $file),
                        'caption' => $caption,
                        'sort_order' => ++$order,
                    ]);
                }
                $filled[] = 'gallery';
            }

            $this->command?->info("{$tenant->name}: ".($filled ? 'added '.implode(', ', $filled) : 'already has its own content, left as is'));
        }
    }

    /**
     * Copies a bundled photo onto the public disk, exactly where an admin upload would land.
     * Each shop gets its own copy, so deleting one shop's photo never breaks another's.
     */
    private function publish(string $directory, string $name, string $source): string
    {
        $path = "{$directory}/{$name}";
        Storage::disk('public')->put($path, file_get_contents(database_path("seeders/images/{$source}")));

        return $path;
    }
}
