<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'status' => OrderStatus::PENDING,
            'total_amount' => 0.00,
            'currency' => 'USD',
            'notes' => fake()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::CANCELLED]);
    }
}
