<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final readonly class ResolveCustomerAction
{
    /**
     * Finds or creates the account that owns a verified phone number.
     *
     * The phone is the identity. Name and e-mail are only filled in for customers,
     * never overwriting an e-mail and never claiming one another account uses.
     */
    public function execute(string $phone, string $name, ?string $email): User
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            try {
                $user = new User(['phone' => $phone, 'name' => $name, 'email' => $this->unclaimed($email)]);
                // Never taken from input: guests can only ever become customers.
                $user->role = UserRole::Customer;
                $user->save();

                return $user;
            } catch (UniqueConstraintViolationException $e) {
                // Lost a race with a concurrent verification for the same phone.
                return User::query()->where('phone', $phone)->first() ?? throw $e;
            }
        }

        if ($user->role === UserRole::Customer) {
            $user->name = $name;
            $user->email ??= $this->unclaimed($email);
            $user->save();
        }

        return $user;
    }

    private function unclaimed(?string $email): ?string
    {
        return $email !== null && User::query()->where('email', $email)->doesntExist() ? $email : null;
    }
}
