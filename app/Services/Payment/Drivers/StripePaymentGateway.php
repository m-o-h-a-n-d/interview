<?php

namespace App\Services\Payment\Drivers;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class StripePaymentGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function process(ProcessPaymentDTO $dto): PaymentResultDTO
    {
        $payload = $dto->gatewayPayload;
        $simulateFailure = $payload['simulate_failure'] ?? false;

        if ($simulateFailure) {
            return PaymentResultDTO::failure(
                transactionId: 'ch_failed_'.Str::random(24),
                gatewayName: $this->getName(),
                message: 'Stripe charge was blocked by fraud protection rules.',
                rawResponse: [
                    'gateway' => 'Stripe',
                    'outcome' => ['risk_level' => 'highest'],
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        $transactionId = 'pi_'.Str::random(24);

        return PaymentResultDTO::success(
            transactionId: $transactionId,
            gatewayName: $this->getName(),
            message: 'Stripe PaymentIntent succeeded.',
            rawResponse: [
                'gateway' => 'Stripe',
                'payment_intent_id' => $transactionId,
                'status' => 'succeeded',
                'amount_received' => (int) ($dto->amount * 100),
                'currency' => strtolower($dto->currency),
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
