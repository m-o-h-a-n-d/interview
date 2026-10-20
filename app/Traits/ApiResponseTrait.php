<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Send a unified successful JSON response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Operation successful.', int $statusCode = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Send a unified error JSON response.
     */
    protected function errorResponse(string $message = 'An error occurred.', int $statusCode = 400, mixed $errors = null, ?string $errorCode = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $payload['error_code'] = $errorCode;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }
}
