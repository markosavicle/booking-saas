<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SmsSender;
use App\Data\BookingDraft;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues and redeems the one-time SMS codes that confirm a booking draft.
 *
 * Only a keyed hash of the code is cached. Each code allows a fixed number of
 * guesses and is consumed on success, so a verification id can book at most once.
 */
final readonly class BookingOtpBroker
{
    public function __construct(
        private Cache $cache,
        private SmsSender $sms,
    ) {}

    /**
     * Stores the draft, texts the code to the draft's phone and returns the verification id.
     */
    public function issue(BookingDraft $draft, string $businessName): string
    {
        $id = (string) Str::uuid();
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $expiresAt = now()->addMinutes($this->ttlMinutes());

        $this->cache->put($this->key($id), [
            'draft' => $draft->toArray(),
            'code_hash' => $this->hash($id, $code),
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        $this->sms->send(
            $draft->phone,
            "{$code} is your {$businessName} booking code. It expires in {$this->ttlMinutes()} minutes. Do not share it.",
        );

        return $id;
    }

    /**
     * Consumes the verification and returns its draft when the code matches.
     *
     * @throws ValidationException When the verification is unknown, expired, exhausted or the code is wrong.
     */
    public function redeem(string $id, string $code): BookingDraft
    {
        // Serialise redemptions of one id so a correct code cannot be used twice concurrently.
        return $this->cache->lock($this->key($id).':lock', 10)->block(5, function () use ($id, $code): BookingDraft {
            $pending = $this->cache->get($this->key($id));

            if ($pending === null) {
                throw ValidationException::withMessages([
                    'code' => 'This code has expired. Please request a new one.',
                ]);
            }

            if (! hash_equals($pending['code_hash'], $this->hash($id, $code))) {
                $attempts = $pending['attempts'] + 1;

                if ($attempts >= $this->maxAttempts()) {
                    $this->cache->forget($this->key($id));

                    throw ValidationException::withMessages([
                        'code' => 'Too many incorrect attempts. Please request a new code.',
                    ]);
                }

                // Keep the original expiry, so wrong guesses never extend a code's life.
                $this->cache->put(
                    $this->key($id),
                    [...$pending, 'attempts' => $attempts],
                    now()->setTimestamp($pending['expires_at']),
                );

                throw ValidationException::withMessages([
                    'code' => 'The code is incorrect.',
                ]);
            }

            $this->cache->forget($this->key($id));

            return BookingDraft::fromArray($pending['draft']);
        });
    }

    private function key(string $id): string
    {
        return "booking-otp:{$id}";
    }

    private function hash(string $id, string $code): string
    {
        return hash_hmac('sha256', "{$id}|{$code}", (string) config('app.key'));
    }

    private function ttlMinutes(): int
    {
        return (int) config('booking.otp.ttl_minutes');
    }

    private function maxAttempts(): int
    {
        return (int) config('booking.otp.max_attempts');
    }
}
