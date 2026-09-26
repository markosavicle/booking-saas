<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;

class BookingPageController extends Controller
{
    /**
     * Shop landing page around the booking widget. The shop's public profile (hours, team)
     * is server-rendered; the widget itself still loads its data client-side from the API.
     * Without a slug the page is a neutral shell over the widget's shop picker.
     */
    public function __invoke(?Tenant $tenant = null): View
    {
        $tenant?->load([
            'businessHours',
            'staffMembers' => fn ($query) => $query->active()->orderBy('name'),
        ]);

        return view('booking', [
            'tenant' => $tenant,
            'initialSlug' => $tenant?->slug,
        ]);
    }
}
