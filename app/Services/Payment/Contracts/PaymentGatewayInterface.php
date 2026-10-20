<?php

namespace App\Services\Payment\Contracts;

use App\DTOs\PaymentResultDTO;
use App\DTOs\ProcessPaymentDTO;

interface PaymentGatewayInterface
{
    /**
     * Get the human-readable or system name of the gateway.
     */
    public function getName(): string;

    /**
     * Process the payment transaction.
     */
    public function process(ProcessPaymentDTO $dto): PaymentResultDTO;
}
