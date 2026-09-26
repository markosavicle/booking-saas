<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->slug ??= static::uniqueSlugFor($tenant->name);
        });
    }

    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /**
     * The current moment on the tenant's wall clock.
     */
    public function localNow(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone);
    }

    /**
     * Interprets a wall-clock string (e.g. "2026-09-28" or "2026-09-28 09:00") in the tenant's zone.
     */
    public function localTime(string $format, string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat($format, $value, $this->timezone);
    }

    /**
     * Opening hours for the given local date, or null when the tenant is closed that day.
     * Uses the eager-loaded relation when available to avoid N+1 queries.
     */
    public function hoursFor(CarbonInterface $date): ?BusinessHour
    {
        if ($this->relationLoaded('businessHours')) {
            return $this->businessHours->firstWhere('day_of_week', $date->dayOfWeek);
        }

        return $this->businessHours()->where('day_of_week', $date->dayOfWeek)->first();
    }

    /**
     * The week Monday-first for display, keyed by day_of_week (0 = Sunday); null marks a closed day.
     *
     * @return Collection<int, BusinessHour|null>
     */
    public function weeklyHours(): Collection
    {
        $hours = $this->businessHours->keyBy('day_of_week');

        return collect([1, 2, 3, 4, 5, 6, 0])->mapWithKeys(fn (int $day): array => [$day => $hours->get($day)]);
    }
}
