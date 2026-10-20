<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway driver that will be used
    | if no specific gateway is specified during checkout/payment requests.
    |
    */
    'default' => env('PAYMENT_DEFAULT_GATEWAY', 'credit_card'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configurations
    |--------------------------------------------------------------------------
    |
    | Configure credentials and options for each supported payment gateway.
    | Adding a new gateway requires simply registering a driver class and its config here.
    |
    */
    'gateways' => [
        'credit_card' => [
            'name' => 'Credit Card',
            'driver' => \App\Services\Payment\Drivers\CreditCardPaymentGateway::class,
            'api_key' => env('CREDIT_CARD_API_KEY', 'test_cc_key_12345'),
            'secret' => env('CREDIT_CARD_SECRET', 'test_cc_secret_67890'),
            'currency' => env('PAYMENT_DEFAULT_CURRENCY', 'USD'),
            'auto_capture' => true,
        ],

        'paypal' => [
            'name' => 'PayPal',
            'driver' => \App\Services\Payment\Drivers\PayPalPaymentGateway::class,
            'client_id' => env('PAYPAL_CLIENT_ID', 'test_paypal_client_id'),
            'secret' => env('PAYPAL_SECRET', 'test_paypal_secret'),
            'sandbox' => env('PAYPAL_SANDBOX', true),
        ],

        'stripe' => [
            'name' => 'Stripe',
            'driver' => \App\Services\Payment\Drivers\StripePaymentGateway::class,
            'public_key' => env('STRIPE_KEY', 'pk_test_sample'),
            'secret_key' => env('STRIPE_SECRET', 'sk_test_sample'),
        ],
    ],
];
