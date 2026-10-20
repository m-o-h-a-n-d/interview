<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'transaction_id' => 'tx_'.Str::random(16),
            'payment_method' => fake()->randomElement(PaymentMethod::values()),
            'amount' => fake()->randomFloat(2, 50, 1000),
            'currency' => 'USD',
            'status' => PaymentStatus::SUCCESSFUL,
            'idempotency_key' => Str::uuid()->toString(),
            'metadata' => ['simulated' => true],
            'error_message' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::SUCCESSFUL, 'error_message' => null]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::FAILED,
            'error_message' => 'Transaction declined by bank.',
        ]);
    }
}
