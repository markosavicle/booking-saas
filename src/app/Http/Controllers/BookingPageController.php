<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Contracts\View\View;

class BookingPageController extends Controller
{
    /**
     * Shell for the booking widget; all data is loaded client-side from the API.
     * An optional tenant slug deep-links straight to that shop.
     */
    public function __invoke(?Tenant $tenant = null): View
    {
        return view('booking', ['initialSlug' => $tenant?->slug]);
    }
}
