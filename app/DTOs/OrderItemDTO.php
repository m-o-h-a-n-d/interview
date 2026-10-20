<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class OrderItemDTO extends Data
{
    public function __construct(
        public string $productName,
        public int $quantity,
        public float $unitPrice,
        public ?float $subtotal = null,
    ) {
        $this->subtotal ??= round($this->quantity * $this->unitPrice, 2);
    }

    public function getCalculatedSubtotal(): float
    {
        return $this->subtotal ?? round($this->quantity * $this->unitPrice, 2);
    }

    public static function fromArray(array $data): self
    {
        $quantity = (int) ($data['quantity'] ?? 1);
        $unitPrice = (float) ($data['unit_price'] ?? $data['unitPrice'] ?? 0.0);
        $subtotal = isset($data['subtotal']) ? (float) $data['subtotal'] : round($quantity * $unitPrice, 2);

        return new self(
            productName: (string) ($data['product_name'] ?? $data['productName'] ?? ''),
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: $subtotal,
        );
    }

    public function toArray(): array
    {
        return [
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->getCalculatedSubtotal(),
        ];
    }
}
