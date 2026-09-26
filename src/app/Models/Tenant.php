<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    /** Directory on the `public` disk that holds uploaded hero images. */
    public const string HERO_DIRECTORY = 'tenants/heroes';

    /** Bundled fallback for shops that haven't uploaded their own hero image. */
    public const string DEFAULT_HERO_IMAGE = 'images/hero-barbershop.jpg';

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'currency',
        'tagline',
        'about_text',
        'address',
        'phone',
        'social_instagram',
        'social_facebook',
        'hero_image_path',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->slug ??= static::uniqueSlugFor($tenant->name);
        });

        // Replaced or removed uploads would otherwise pile up on the public disk forever.
        static::updated(function (Tenant $tenant): void {
            if ($tenant->wasChanged('hero_image_path')) {
                static::deleteHeroImage($tenant->getRawOriginal('hero_image_path'));
            }
        });

        static::deleted(fn (Tenant $tenant) => static::deleteHeroImage($tenant->hero_image_path));
    }

    private static function deleteHeroImage(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
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

    public function heroImageUrl(): string
    {
        return $this->hero_image_path
            ? Storage::disk('public')->url($this->hero_image_path)
            : asset(self::DEFAULT_HERO_IMAGE);
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return $this->splitLines($this->address, '/\R/');
    }

    /**
     * @return list<string>
     */
    public function aboutParagraphs(): array
    {
        return $this->splitLines($this->about_text, '/\R\s*\R/');
    }

    public function mapsUrl(): ?string
    {
        return $this->address
            ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(implode(', ', $this->addressLines()))
            : null;
    }

    public function phoneHref(): ?string
    {
        return $this->phone ? 'tel:'.preg_replace('/[^\d+]/', '', $this->phone) : null;
    }

    /**
     * @return list<string>
     */
    private function splitLines(?string $text, string $separator): array
    {
        return collect(preg_split($separator, trim((string) $text)))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}
