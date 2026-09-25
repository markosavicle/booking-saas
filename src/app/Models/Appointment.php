<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Traits\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'service_id',
        'user_id',
        'start_time',
        'end_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Appointments that occupy a calendar slot (i.e. not canceled).
     */
    #[Scope]
    protected function blocking(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('status'), AppointmentStatus::blocking());
    }

    /**
     * Half-open interval overlap: [start, end) intersects [start_time, end_time).
     * Back-to-back appointments (one ends exactly when the next starts) do not overlap.
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where($this->qualifyColumn('start_time'), '<', $end)
            ->where($this->qualifyColumn('end_time'), '>', $start);
    }
}
