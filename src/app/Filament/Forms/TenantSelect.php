<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

/**
 * The "Shop" field of a tenant-owned record. Super admins pick any shop; a shop admin's records
 * always belong to their own shop (enforced again by BelongsToTenant), so they never see the field.
 */
final class TenantSelect
{
    public static function make(): Select
    {
        return Select::make('tenant_id')
            ->label('Shop')
            ->relationship('tenant', 'name', fn (Builder $query): Builder => $query
                ->when(self::shopAdmin(), fn (Builder $query, User $admin) => $query->whereKey($admin->tenant_id)))
            ->default(fn (): ?int => self::shopAdmin()?->tenant_id)
            ->hidden(fn (): bool => self::shopAdmin() !== null)
            ->searchable()
            ->preload()
            ->live()
            ->required();
    }

    public static function shopAdmin(): ?User
    {
        $user = auth()->user();

        return $user instanceof User && $user->isTenantAdmin() ? $user : null;
    }
}
