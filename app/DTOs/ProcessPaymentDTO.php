<?php

namespace App\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class ProcessPaymentDTO extends Data
{
    public function __construct(
        #[MapInputName('order_id')]
        public int $orderId,

        #[MapInputName('payment_method')]
        public string $paymentMethod,

        public float $amount,

        public string $currency = 'USD',

        #[MapInputName('idempotency_key')]
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
