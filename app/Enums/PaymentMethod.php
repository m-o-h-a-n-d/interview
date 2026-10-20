<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CREDIT_CARD = 'credit_card';
    case PAYPAL = 'paypal';
    case STRIPE = 'stripe';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
