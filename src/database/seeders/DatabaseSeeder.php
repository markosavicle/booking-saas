<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * `hero` names a stock photo in database/seeders/images; null exercises the bundled default.
     */
    private const array TENANTS = [
        [
            'name' => 'Belgrade Central Cuts',
            'city' => 'Belgrade',
            'hero' => 'barbershop-interior.jpg',
            'profile' => [
                'tagline' => 'Straight-razor shaves and sharp fades a block from Republic Square.',
                'about_text' => "Exposed brick, leather chairs and a record player that never stops. We've been cutting Dorćol's hair since 2014, and every appointment still starts with a proper consultation.\n\nCome in early for an espresso on the house.",
                'address' => "Kneza Mihaila 12\n11000 Belgrade, Serbia",
                'phone' => '+381 11 328 4471',
                'social_instagram' => 'https://www.instagram.com/belgradecentralcuts',
                'social_facebook' => 'https://www.facebook.com/belgradecentralcuts',
            ],
        ],
        [
            'name' => 'Novi Sad Fade Studio',
            'city' => 'Novi Sad',
            'hero' => 'barbershop-fade.jpg',
            'profile' => [
                'tagline' => 'Skin fades, textured crops and beard work for the Danube crowd.',
                'about_text' => "A small, loud studio off Zmaj Jovina where fades are measured in millimetres. Our barbers train every month so the latest styles reach Novi Sad before they're everywhere.\n\nWalk-ins welcome when the chairs are free; booking guarantees your slot.",
                'address' => "Zmaj Jovina 8\n21000 Novi Sad, Serbia",
                'phone' => '+381 21 661 2093',
                'social_instagram' => 'https://www.instagram.com/novisadfadestudio',
                'social_facebook' => null,
            ],
        ],
        [
            'name' => 'Niš Classic Barbers',
            'city' => 'Niš',
            'hero' => null,
            'profile' => [
                'tagline' => 'Old-school cuts and hot towel shaves in the heart of Niš.',
                'about_text' => 'Three generations of barbers, one chair at a time. Classic scissor cuts, hot towels and a neckline you can set your watch by.',
                'address' => "Obrenovićeva 21\n18000 Niš, Serbia",
                'phone' => '+381 18 452 118',
                'social_instagram' => null,
                'social_facebook' => 'https://www.facebook.com/nisclassicbarbers',
            ],
        ],
    ];

    private const array SERVICES = [
        ['name' => 'Signature Haircut', 'duration_minutes' => 30, 'price' => 25.00],
        ['name' => 'Beard Sculpting', 'duration_minutes' => 30, 'price' => 15.00],
        ['name' => 'VIP Full Treatment', 'duration_minutes' => 60, 'price' => 50.00],
    ];

    /**
     * Staff per tenant; `null` means qualified for every service.
     *
     * @var array<string, list<string>|null>
     */
    private const array STAFF = [
        'Marko' => null,
        'Stefan' => null,
        'Luka' => ['Signature Haircut', 'Beard Sculpting'],
    ];

    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);

        // Customers are global: they can book at any tenant.
        $customers = User::factory(5)->create();

        $bookingDay = $this->nextWorkingDay();

        foreach (self::TENANTS as $data) {
            $tenant = Tenant::factory()->withStandardHours('09:00', '21:00')->create([
                'name' => $data['name'],
                'timezone' => 'Europe/Belgrade',
                'currency' => 'EUR',
                'hero_image_path' => $data['hero'] ? $this->publishHeroImage($data['hero']) : null,
                ...$data['profile'],
            ]);

            User::factory()->tenantAdmin($tenant)->create([
                'name' => "{$data['city']} Admin",
                'email' => Str::slug($data['city']).'@example.com',
            ]);

            $services = collect(self::SERVICES)->map(
                fn (array $service): Service => Service::factory()->for($tenant)->create($service),
            );

            $staff = collect(self::STAFF)->map(
                function (?array $serviceNames, string $name) use ($tenant, $services): StaffMember {
                    $member = StaffMember::factory()->for($tenant)->create(['name' => $name]);
                    $member->services()->attach(
                        $services->filter(fn (Service $service): bool => $serviceNames === null
                            || in_array($service->name, $serviceNames, true))->pluck('id')->all(),
                    );

                    return $member;
                },
            )->values();

            // Pre-booked slots on the next working day to exercise availability.
            foreach ([['10:00', $services[0], $staff[0]], ['14:00', $services[1], $staff[1]]] as [$time, $service, $member]) {
                // Wall-clock time at the shop, stored as UTC.
                $start = $bookingDay->shiftTimezone($tenant->timezone)->setTimeFromTimeString($time)->utc();

                Appointment::factory()->forService($service)->create([
                    'staff_member_id' => $member->id,
                    'user_id' => $customers->random()->id,
                    'start_time' => $start,
                    'end_time' => $start->addMinutes($service->duration_minutes),
                    'status' => AppointmentStatus::Confirmed,
                ]);
            }
        }
    }

    /**
     * Copies a bundled stock photo onto the public disk, exactly where an admin upload would land.
     */
    private function publishHeroImage(string $filename): string
    {
        $path = Tenant::HERO_DIRECTORY.'/'.$filename;
        Storage::disk('public')->put($path, file_get_contents(database_path("seeders/images/{$filename}")));

        return $path;
    }

    private function nextWorkingDay(): CarbonImmutable
    {
        $day = CarbonImmutable::tomorrow();

        return $day->isSunday() ? $day->addDay() : $day;
    }
}
