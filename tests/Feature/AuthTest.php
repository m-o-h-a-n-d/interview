<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_successfully(): void
    {
        $payload = [
            'name' => 'Alice Wonder',
            'email' => 'alice@example.com',
            'password' => 'secret1234',
        ];

        $response = $this->postJson(route('auth.register'), $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
            'name' => 'Alice Wonder',
            'role' => UserRole::USER->value,
        ]);
    }

    public function test_user_registration_fails_on_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $payload = [
            'name' => 'Duplicate Person',
            'email' => 'existing@example.com',
            'password' => 'secret1234',
        ];

        $response = $this->postJson(route('auth.register'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login_user@example.com',
            'password' => 'correct_pass_123',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'login_user@example.com',
            'password' => 'correct_pass_123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User logged in successfully.',
            ])
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type'],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@example.com',
            'password' => 'correct_password',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'valid@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_authenticated_user_can_access_profile(): void
    {
        $auth = $this->authenticateUser();

        $response = $this->getJson(route('auth.me'), $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $auth['user']->id,
                    'email' => $auth['user']->email,
                ],
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('auth.me'), ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_refresh_token(): void
    {
        $auth = $this->authenticateUser();

        $response = $this->postJson(route('auth.refresh'), [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'expires_in'],
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $auth = $this->authenticateUser();

        $response = $this->postJson(route('auth.logout'), [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
