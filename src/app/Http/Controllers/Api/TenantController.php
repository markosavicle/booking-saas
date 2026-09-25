<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;

class TenantController extends Controller
{
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
