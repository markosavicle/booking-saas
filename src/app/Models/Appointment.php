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
use Illuminate\Support\Str;

class Appointment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'service_id',
        'staff_member_id',
        'user_id',
        'email',
        'start_time',
        'end_time',
        'status',
        'reminder_sent_at',
    ];

    /** Bearer secret for the public cancel link; never serialised. */
    protected $hidden = [
        'cancel_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment): void {
            $appointment->cancel_token ??= Str::random(48);
        });
    }

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    public function cancelUrl(): string
    {
        return route('booking.cancel', $this->cancel_token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
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
     * In-memory counterpart of the overlapping() scope for already-loaded models.
     */
    public function overlaps(CarbonInterface $start, CarbonInterface $end): bool
    {
        return $this->start_time < $end && $this->end_time > $start;
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
