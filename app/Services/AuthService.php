<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Register a new user and generate a JWT token.
     */
    public function register(array $data): array
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? UserRole::USER->value,
        ]);

        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }

    /**
     * Authenticate user credentials and return JWT token.
     *
     * @throws AuthenticationException
     */
    public function login(array $credentials): array
    {
        if (! $token = auth('api')->attempt($credentials)) {
            throw new AuthenticationException('Invalid email or password credentials provided.');
        }

        /** @var User $user */
        $user = auth('api')->user();

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(): ?User
    {
        /** @var User|null $user */
        $user = auth('api')->user();

        return $user;
    }

    /**
     * Refresh the existing JWT token.
     */
    public function refresh(): array
    {
        $newToken = auth('api')->refresh();

        return [
            'user' => auth('api')->user(),
            'token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ];
    }

    /**
     * Log out the current user and invalidate the JWT token.
     */
    public function logout(): void
    {
        auth('api')->logout();
    }
}
