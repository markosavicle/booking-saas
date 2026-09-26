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
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    private const array TENANTS = [
        ['name' => 'Belgrade Central Cuts', 'city' => 'Belgrade'],
        ['name' => 'Novi Sad Fade Studio', 'city' => 'Novi Sad'],
        ['name' => 'Niš Classic Barbers', 'city' => 'Niš'],
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

    private function nextWorkingDay(): CarbonImmutable
    {
        $day = CarbonImmutable::tomorrow();

        return $day->isSunday() ? $day->addDay() : $day;
    }
}
