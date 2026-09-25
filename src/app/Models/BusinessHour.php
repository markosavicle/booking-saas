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

    public function opensOn(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$this->opens_at);
    }

    public function closesOn(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString().' '.$this->closes_at);
    }
}
