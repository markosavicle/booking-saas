<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * `role` and `tenant_id` are deliberately NOT mass-assignable to prevent
     * privilege escalation via request payloads. Assign them explicitly.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Destination for the SMS notification channel; null disables SMS for this user.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isTenantAdmin(): bool
    {
        return $this->role === UserRole::TenantAdmin;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->isTenantAdmin();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }
}
