<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final readonly class RegisterCustomerAction
{
    /**
     * @param  array{name: string, email: string, phone?: ?string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        $user = new User($attributes);
        // Never taken from input: self-registration can only ever create customers.
        $user->role = UserRole::Customer;
        $user->save();

        event(new Registered($user));

        return $user;
    }
}
