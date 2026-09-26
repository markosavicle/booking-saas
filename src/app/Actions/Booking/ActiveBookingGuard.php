<?php

declare(strict_types=1);

namespace App\Actions\Booking;

use App\Models\Appointment;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * One upcoming appointment per customer per shop, so a phone number can't hoard a calendar.
 */
final readonly class ActiveBookingGuard
{
    public const string MESSAGE = 'You already have an active appointment at this shop. Please cancel your existing appointment before booking a new one.';

    /**
     * Cheap pre-check before an SMS is spent; the phone may not belong to an account yet.
     *
     * @throws ValidationException
     */
    public function ensureNoneForPhone(string $phone, int $tenantId): void
    {
        $customer = User::query()->where('phone', $phone)->first(['id']);

        if ($customer !== null) {
            $this->ensureNoneFor($customer, $tenantId);
        }
    }

    /**
     * Authoritative check; call inside the booking transaction with the customer row locked.
     *
     * @throws ValidationException
     */
    public function ensureNoneFor(User $customer, int $tenantId): void
    {
        // Filtered by tenant explicitly: a shop admin signed in on the same browser must not narrow it.
        $hasActive = Appointment::withoutGlobalScope(TenantScope::class)
            ->blocking()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $customer->id)
            ->where('start_time', '>', now())
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages(['phone' => self::MESSAGE]);
        }
    }
}
