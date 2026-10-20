<?php

namespace App\DTOs;

readonly class UpdateOrderDTO
{
    /**
     * @param OrderItemDTO[]|null $items
     */
    public function __construct(
        public ?string $customerName = null,
        public ?string $customerEmail = null,
        public ?string $customerPhone = null,
        public ?string $status = null,
        public ?string $notes = null,
        public ?array $items = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        $items = null;
        if (isset($data['items']) && is_array($data['items'])) {
            $items = [];
            foreach ($data['items'] as $itemData) {
                $items[] = OrderItemDTO::fromArray($itemData);
            }
        }

        return new self(
            customerName: $data['customer_name'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            status: $data['status'] ?? null,
            notes: $data['notes'] ?? null,
            items: $items,
        );
    }
}
