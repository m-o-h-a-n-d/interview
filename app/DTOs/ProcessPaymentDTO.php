<?php

namespace App\DTOs;

readonly class ProcessPaymentDTO
{
    public function __construct(
        public int $orderId,
        public string $paymentMethod,
        public float $amount,
        public string $currency = 'USD',
        public ?string $idempotencyKey = null,
        public array $gatewayPayload = [],
    ) {}

    public static function fromRequest(array $data, int $orderId, float $orderAmount, string $orderCurrency, ?string $idempotencyKey = null): self
    {
        return new self(
            orderId: $orderId,
            paymentMethod: strtolower((string) ($data['payment_method'] ?? 'credit_card')),
            amount: isset($data['amount']) ? (float) $data['amount'] : $orderAmount,
            currency: $data['currency'] ?? $orderCurrency,
            idempotencyKey: $idempotencyKey ?? ($data['idempotency_key'] ?? null),
            gatewayPayload: $data['gateway_details'] ?? $data['payment_details'] ?? [],
        );
    }
}
