<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMember extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Whether customers are still due in this barber's chair. Uses a `withExists` value
     * named has_upcoming_appointments when the query loaded one.
     */
    public function hasUpcomingAppointments(): bool
    {
        return (bool) ($this->attributes['has_upcoming_appointments'] ?? $this->appointments()
            ->whereIn('status', AppointmentStatus::blocking())
            ->where('start_time', '>', now())
            ->exists());
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }
}
