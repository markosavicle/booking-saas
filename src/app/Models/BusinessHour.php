<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Opening instant (UTC) for $date, a date in the tenant's timezone.
     */
    public function opensOn(CarbonInterface $date): CarbonImmutable
    {
        return $this->onDate($date, $this->opens_at);
    }

    /**
     * Closing instant (UTC) for $date, a date in the tenant's timezone.
     */
    public function closesOn(CarbonInterface $date): CarbonImmutable
    {
        return $this->onDate($date, $this->closes_at);
    }

    /**
     * Hours are wall-clock times, so they are resolved in the date's own zone and then
     * normalised to UTC, which is what the database stores and what queries bind.
     */
    private function onDate(CarbonInterface $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$time, $date->getTimezone())->utc();
    }
}
