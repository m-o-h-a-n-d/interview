<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderHasPaymentsException extends Exception
{
    public function __construct(string $message = "Orders cannot be deleted if they have associated payments.", int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'ORDER_HAS_PAYMENTS',
        ], $this->getCode() ?: 422);
    }
}
