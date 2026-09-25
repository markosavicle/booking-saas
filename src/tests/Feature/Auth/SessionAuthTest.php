<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

class SessionAuthTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+381601234567',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            ...$overrides,
        ]);
    }

    public function test_customers_can_register_and_are_signed_in(): void
    {
        $this->register()
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.phone', '+381601234567')
            ->assertJsonMissingPath('data.role');

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Customer, $user->role);
    }

    public function test_registration_cannot_escalate_privileges(): void
    {
        $this->register(['role' => 'superadmin', 'tenant_id' => 1])->assertCreated();

        $this->assertSame(UserRole::Customer, User::sole()->role);
        $this->assertNull(User::sole()->tenant_id);
    }

    public function test_registration_is_validated(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->register(['phone' => '0601234567', 'password_confirmation' => 'different'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'password']);
        $this->assertGuest();
    }

    public function test_customers_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret-password']);

        $this->postJson('/auth/login', ['email' => 'jane@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
        $this->assertAuthenticatedAs($user);

        $this->postJson('/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_wrong_credentials_are_rejected_and_throttled(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret-password']);
        $attempt = fn () => $this->postJson('/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong']);

        foreach (range(1, 5) as $ignored) {
            $attempt()->assertUnprocessable()->assertJsonValidationErrors(['email' => 'do not match']);
        }

        $attempt()->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Too many login attempts']);
        $this->assertGuest();
    }

    public function test_the_api_accepts_the_session_cookie_from_the_first_party_frontend(): void
    {
        $this->assertContains(EnsureFrontendRequestsAreStateful::class, $this->app['router']->getMiddlewareGroups()['api']);
    }

    public function test_the_current_user_endpoint_exposes_only_public_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ]]);
    }
}
