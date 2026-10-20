<?php

namespace App\DTOs;

use App\Enums\PaymentStatus;

readonly class PaymentResultDTO
{
    public function __construct(
        public bool $isSuccess,
        public string $transactionId,
        public PaymentStatus $status,
        public string $gatewayName,
        public ?string $message = null,
        public array $rawResponse = [],
    ) {}

    public static function success(string $transactionId, string $gatewayName, string $message = 'Payment captured successfully.', array $rawResponse = []): self
    {
        return new self(
            isSuccess: true,
            transactionId: $transactionId,
            status: PaymentStatus::SUCCESSFUL,
            gatewayName: $gatewayName,
            message: $message,
            rawResponse: $rawResponse,
        );
    }

    public static function failure(string $transactionId, string $gatewayName, string $message = 'Payment processing failed.', array $rawResponse = []): self
    {
        return new self(
            isSuccess: false,
            transactionId: $transactionId,
            status: PaymentStatus::FAILED,
            gatewayName: $gatewayName,
            message: $message,
            rawResponse: $rawResponse,
        );
    }
}
