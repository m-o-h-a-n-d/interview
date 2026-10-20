<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponseTrait;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ], 'User registered successfully.', 201);
    }

    /**
     * Authenticate user credentials and return JWT token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
                'expires_in' => $result['expires_in'],
            ], 'User logged in successfully.');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        }
    }

    /**
     * Get profile of authenticated user.
     */
    public function me(): JsonResponse
    {
        $user = $this->authService->me();

        if (! $user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        return $this->successResponse(new UserResource($user), 'User profile retrieved successfully.');
    }

    /**
     * Refresh JWT token.
     */
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return $this->successResponse([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
            'expires_in' => $result['expires_in'],
        ], 'Token refreshed successfully.');
    }

    /**
     * Log out authenticated user.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->successResponse(null, 'User successfully logged out.');
    }
}
