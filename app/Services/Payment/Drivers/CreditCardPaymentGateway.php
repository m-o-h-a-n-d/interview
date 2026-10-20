<?php

namespace App\Services\Payment\Drivers;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class CreditCardPaymentGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'credit_card';
    }

    public function process(ProcessPaymentDTO $dto): PaymentResultDTO
    {
        $payload = $dto->gatewayPayload;

        // Allow simulation of payment failure via test card or flag
        $cardNumber = $payload['card_number'] ?? null;
        $simulateFailure = $payload['simulate_failure'] ?? false;

        // Test card ending in '0002' or explicit simulate_failure fails
        if ($simulateFailure || ($cardNumber && str_ends_with((string) $cardNumber, '0002'))) {
            return PaymentResultDTO::failure(
                transactionId: 'cc_failed_'.Str::random(16),
                gatewayName: $this->getName(),
                message: 'Credit card transaction declined: Insufficient funds or invalid card security code.',
                rawResponse: [
                    'gateway' => 'CreditCard',
                    'auth_code' => null,
                    'error' => 'CARD_DECLINED',
                    'timestamp' => now()->toIso8601String(),
                ]
            );
        }

        // Successful transaction simulation
        $transactionId = 'cc_tx_'.Str::uuid();

        return PaymentResultDTO::success(
            transactionId: $transactionId,
            gatewayName: $this->getName(),
            message: 'Credit card charged successfully.',
            rawResponse: [
                'gateway' => 'CreditCard',
                'auth_code' => strtoupper(Str::random(6)),
                'amount' => $dto->amount,
                'currency' => $dto->currency,
                'card_last4' => $cardNumber ? substr((string) $cardNumber, -4) : '4242',
                'timestamp' => now()->toIso8601String(),
            ]
        );
    }
}
