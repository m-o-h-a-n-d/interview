<?php

namespace App\DTOs;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class UpdateOrderDTO extends Data
{
    /**
     * @param OrderItemDTO[]|null $items
     */
    public function __construct(
        #[MapInputName('customer_name')]
        public ?string $customerName = null,

        #[MapInputName('customer_email')]
        public ?string $customerEmail = null,

        #[MapInputName('customer_phone')]
        public ?string $customerPhone = null,

        public ?string $status = null,

        public ?string $notes = null,

        #[DataCollectionOf(OrderItemDTO::class)]
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
            customerName: $data['customer_name'] ?? $data['customerName'] ?? null,
            customerEmail: $data['customer_email'] ?? $data['customerEmail'] ?? null,
            customerPhone: $data['customer_phone'] ?? $data['customerPhone'] ?? null,
            status: $data['status'] ?? null,
            notes: $data['notes'] ?? null,
            items: $items,
        );
    }
}
