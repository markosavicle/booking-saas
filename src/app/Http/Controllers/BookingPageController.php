<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Scopes\TenantScope;
use App\Models\StaffMember;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class BookingPageController extends Controller
{
    /**
     * Shop landing page around the booking widget. The shop's public profile (hours, team,
     * gallery, FAQ) is server-rendered; the widget itself still loads its data client-side
     * from the API. Without a slug the page is a directory of shops over the widget's shop
     * picker, plus a sample of barbers from across the platform.
     */
    public function __invoke(?Tenant $tenant = null): View
    {
        // Public page: a signed-in shop admin browsing another shop must still see all of it.
        $tenant?->load([
            'businessHours',
            'staffMembers' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->active()->orderBy('name'),
            'galleryImages' => fn ($query) => $query->withoutGlobalScope(TenantScope::class),
        ]);

        return view('booking', [
            'tenant' => $tenant,
            'initialSlug' => $tenant?->slug,
            'staff' => $tenant?->staffMembers ?? $this->featuredStaff(),
            'shops' => $tenant ? new Collection : $this->shops(),
            'gallery' => $tenant?->galleryImages ?? new Collection,
            'faqs' => $tenant?->faqItems() ?? Tenant::defaultFaqs(),
        ]);
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function shops(): Collection
    {
        return Tenant::query()
            ->select(['id', 'name', 'slug', 'tagline', 'address', 'hero_image_path'])
            ->withCount(['staffMembers' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->active()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, StaffMember>
     */
    private function featuredStaff(): Collection
    {
        // Public page: a signed-in shop admin must still see the whole platform, not just their shop.
        return StaffMember::withoutGlobalScope(TenantScope::class)
            ->active()
            ->with('tenant:id,name,slug')
            ->inRandomOrder()
            ->limit(6)
            ->get();
    }
}
