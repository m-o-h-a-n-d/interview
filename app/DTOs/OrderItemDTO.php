<?php

namespace App\DTOs;

readonly class OrderItemDTO
{
    public function __construct(
        public string $productName,
        public int $quantity,
        public float $unitPrice,
        public ?float $subtotal = null,
    ) {}

    public function getCalculatedSubtotal(): float
    {
        return $this->subtotal ?? round($this->quantity * $this->unitPrice, 2);
    }

    public static function fromArray(array $data): self
    {
        $quantity = (int) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];

        return new self(
            productName: (string) $data['product_name'],
            quantity: $quantity,
            unitPrice: $unitPrice,
            subtotal: isset($data['subtotal']) ? (float) $data['subtotal'] : round($quantity * $unitPrice, 2),
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
