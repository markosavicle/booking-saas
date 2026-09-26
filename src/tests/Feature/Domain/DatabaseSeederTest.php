<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\GalleryImage;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The seeders publish photos to the public disk; never write them to the real one.
        Storage::fake('public');
    }

    public function test_seeder_builds_a_consistent_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Tenant::count());
        $this->assertSame(1, User::where('role', UserRole::SuperAdmin)->count());

        Tenant::with(['users', 'services.staffMembers', 'staffMembers', 'businessHours', 'appointments'])->get()
            ->each(function (Tenant $tenant): void {
                $this->assertSame(
                    [UserRole::TenantAdmin],
                    $tenant->users->pluck('role')->unique()->values()->all(),
                );
                $this->assertCount(3, $tenant->services);
                $this->assertCount(6, $tenant->businessHours);
                $this->assertCount(2, $tenant->appointments);
                $this->assertCount(3, $tenant->staffMembers);
                $this->assertTrue($tenant->services->every(
                    fn ($service): bool => $service->staffMembers->isNotEmpty()
                        && $service->staffMembers->every(fn ($staff): bool => $staff->tenant_id === $tenant->id),
                ));
            });
    }

    public function test_seeded_shops_have_landing_page_profiles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenants = Tenant::orderBy('id')->get();

        $this->assertTrue($tenants->every(fn (Tenant $tenant): bool => filled($tenant->tagline)
            && count($tenant->addressLines()) === 2
            && filled($tenant->phone)
            && filled($tenant->about_text)));

        // Each shop looks different when you switch between them.
        $heroes = $tenants->pluck('hero_image_path');
        $this->assertCount(3, $heroes->filter()->unique());
        $heroContents = $heroes->map(fn (string $path): string => md5(Storage::disk('public')->get($path)));
        $this->assertCount(3, $heroContents->unique(), 'Every shop needs a different photo, not the same file under three names.');

        foreach ($tenants as $tenant) {
            $this->assertGreaterThanOrEqual(4, $tenant->galleryImages()->count());
            $tenant->galleryImages->each(function (GalleryImage $image): void {
                $this->assertNotEmpty($image->caption);
                Storage::disk('public')->assertExists($image->path);
            });
            $this->assertGreaterThanOrEqual(6, count($tenant->faqItems()));
            $this->assertNotSame(Tenant::defaultFaqs(), $tenant->faqItems(), 'Demo shops should show their own FAQ.');
        }

        // No photo is shared between shops, so one admin deleting theirs can't break another shop's page.
        $this->assertSame(GalleryImage::count(), GalleryImage::distinct()->count('path'));
    }

    public function test_demo_content_only_fills_gaps_and_can_be_rerun(): void
    {
        $belgrade = Tenant::factory()->create(['name' => 'Belgrade Central Cuts', 'hero_image_path' => 'tenants/heroes/own.jpg']);
        $noviSad = Tenant::factory()->create(['name' => 'Novi Sad Fade Studio', 'faqs' => [['question' => 'Own?', 'answer' => 'Yes.']]]);
        GalleryImage::factory()->for($noviSad)->create(['caption' => 'Uploaded by the shop']);
        $other = Tenant::factory()->create(['name' => 'Someone Else']);

        $this->seed(DemoContentSeeder::class);
        $this->seed(DemoContentSeeder::class);

        $this->assertSame('tenants/heroes/own.jpg', $belgrade->refresh()->hero_image_path);
        $this->assertCount(5, $belgrade->galleryImages, 'A rerun must not duplicate the gallery.');
        $this->assertNotSame(Tenant::defaultFaqs(), $belgrade->faqItems());

        $this->assertNotNull($noviSad->refresh()->hero_image_path);
        $this->assertSame([['question' => 'Own?', 'answer' => 'Yes.']], $noviSad->faqItems());
        $this->assertSame(['Uploaded by the shop'], $noviSad->galleryImages->pluck('caption')->all());

        $this->assertNull($other->refresh()->hero_image_path);
        $this->assertSame(0, $other->galleryImages()->count());
    }

    public function test_tenant_admin_emails_are_ascii(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::where('email', 'nis@example.com')->exists());
    }

    public function test_seeded_appointments_are_consistent_and_bookable(): void
    {
        $this->seed(DatabaseSeeder::class);

        Appointment::with(['user', 'service', 'staffMember.services', 'tenant.businessHours'])->get()
            ->each(function (Appointment $appointment): void {
                $this->assertSame(UserRole::Customer, $appointment->user->role);
                $this->assertSame($appointment->tenant_id, $appointment->service->tenant_id);
                $this->assertTrue($appointment->staffMember->services->contains($appointment->service));
                $this->assertNotNull(
                    $appointment->tenant->hoursFor($appointment->start_time),
                    'Seeded appointment falls on a closed day.',
                );
            });
    }
}
