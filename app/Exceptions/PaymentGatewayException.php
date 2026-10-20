<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayException extends Exception
{
    protected ?array $gatewayDetails;

    public function __construct(string $message = "Payment gateway error occurred.", int $code = 400, ?array $gatewayDetails = null)
    {
        parent::__construct($message, $code);
        $this->gatewayDetails = $gatewayDetails;
    }

    public function getGatewayDetails(): ?array
    {
        return $this->gatewayDetails;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => 'PAYMENT_FAILED',
            'details' => $this->gatewayDetails,
        ], $this->getCode() ?: 400);
    }
}
