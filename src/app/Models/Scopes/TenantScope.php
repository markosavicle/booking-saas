<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts tenant admins to their own tenant's records.
 *
 * Customers are global (they book across tenants) and super admins see everything,
 * so neither is scoped. Public/customer queries must filter by tenant explicitly.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user instanceof User && $user->isTenantAdmin()) {
            $builder->where($model->qualifyColumn('tenant_id'), $user->tenant_id);
        }
    }
}
