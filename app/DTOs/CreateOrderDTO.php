<?php

namespace App\DTOs;

use Spatie\LaravelData\Data;

class CreateOrderDTO extends Data
{
    /**
     * @param OrderItemDTO[] $items
     */
    public function __construct(
        public int $userId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone = null,
        public string $currency = 'USD',
        public ?string $notes = null,
        public array $items = [],
    ) {}

    public function calculateTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $subtotal = $item instanceof OrderItemDTO
                ? $item->getCalculatedSubtotal()
                : round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
            $total += $subtotal;
        }

        return round($total, 2);
    }

    public static function fromRequest(array $data, int $userId): self
    {
        $items = [];
        if (! empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $itemData) {
                $items[] = OrderItemDTO::fromArray($itemData);
            }
        }

        return new self(
            userId: $userId,
            customerName: (string) ($data['customer_name'] ?? $data['customerName'] ?? ''),
            customerEmail: (string) ($data['customer_email'] ?? $data['customerEmail'] ?? ''),
            customerPhone: $data['customer_phone'] ?? $data['customerPhone'] ?? null,
            currency: $data['currency'] ?? 'USD',
            notes: $data['notes'] ?? null,
            items: $items,
        );
    }
}
