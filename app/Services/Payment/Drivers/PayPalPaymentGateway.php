<?php

namespace App\Services\Payment\Drivers;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class PayPalPaymentGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function process(ProcessPaymentDTO $dto): PaymentResultDTO
    {
        $payload = $dto->gatewayPayload;
        $paypalEmail = $payload['paypal_email'] ?? $payload['payer_email'] ?? null;
        $simulateFailure = $payload['simulate_failure'] ?? false;

        if ($simulateFailure || ($paypalEmail && str_contains((string) $paypalEmail, 'fail'))) {
            return PaymentResultDTO::failure(
                transactionId: 'pp_failed_'.Str::random(16),
                gatewayName: $this->getName(),
                message: 'PayPal payment capture rejected by payer or account restricted.',
                rawResponse: [
                    'gateway' => 'PayPal',
                    'error' => 'INSTRUMENT_DECLINED',
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        $transactionId = 'PAYPAL_ORDER_'.strtoupper(Str::random(17));

        return PaymentResultDTO::success(
            transactionId: $transactionId,
            gatewayName: $this->getName(),
            message: 'PayPal payment successfully approved and captured.',
            rawResponse: [
                'gateway' => 'PayPal',
                'payer_email' => $paypalEmail ?? 'buyer@example.com',
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'status' => 'COMPLETED',
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
