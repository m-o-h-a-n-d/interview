<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if payment is allowed for this status.
     * Business rule: Payments can only be processed for orders in confirmed status.
     */
    public function canAcceptPayment(): bool
    {
        return $this === self::CONFIRMED;
    }
}
