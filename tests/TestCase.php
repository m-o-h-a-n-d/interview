<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate a standard user and return authorization headers.
     *
     * @return array{user: User, token: string, headers: array<string, string>}
     */
    protected function authenticateUser(?User $user = null): array
    {
        if (auth('api')->check()) {
            auth('api')->logout();
        }
        app('auth')->forgetGuards();
        $user = $user ?? User::factory()->create(['role' => UserRole::USER]);
        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ],
        ];
    }

    /**
     * Authenticate an admin user and return authorization headers.
     *
     * @return array{admin: User, token: string, headers: array<string, string>}
     */
    protected function authenticateAdmin(?User $admin = null): array
    {
        if (auth('api')->check()) {
            auth('api')->logout();
        }
        app('auth')->forgetGuards();
        $admin = $admin ?? User::factory()->create(['role' => UserRole::ADMIN]);
        $token = JWTAuth::fromUser($admin);

        return [
            'admin' => $admin,
            'token' => $token,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ],
        ];
    }
}
