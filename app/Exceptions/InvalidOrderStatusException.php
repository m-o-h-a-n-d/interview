<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidOrderStatusException extends Exception
{
    public function __construct(string $message = "Payments can only be processed for orders in the confirmed status.", int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'INVALID_ORDER_STATUS',
        ], $this->getCode() ?: 422);
    }
}
