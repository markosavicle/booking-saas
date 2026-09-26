<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantController extends Controller
{
    /**
     * Public shop directory for the booking widget's first step.
     */
    public function index(): AnonymousResourceCollection
    {
        return TenantResource::collection(Tenant::query()->orderBy('name')->get(['id', 'name', 'slug', 'timezone']));
    }

    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant->load([
            'businessHours',
            'services' => fn ($query) => $query->active()->orderBy('name')
                ->with(['staffMembers' => fn ($query) => $query->active()]),
            'staffMembers' => fn ($query) => $query->active()->orderBy('name'),
        ]));
    }
}
