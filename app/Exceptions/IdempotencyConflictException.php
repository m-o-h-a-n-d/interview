<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyConflictException extends Exception
{
    public function __construct(string $message = "A request with this Idempotency-Key is currently being processed. Please retry in a few moments.", int $code = 409)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'IDEMPOTENCY_CONFLICT',
        ], 409);
    }
}
