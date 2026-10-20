<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password123'),
                'role' => UserRole::ADMIN,
            ]
        );

        // 2. Create Standard Customer User
        $customer = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'John Doe Customer',
                'password' => Hash::make('password123'),
                'role' => UserRole::USER,
            ]
        );

        // 3. Create Sample Orders for Customer
        // A) Confirmed Order with Payment
        $confirmedOrder = Order::create([
            'user_id' => $customer->id,
            'customer_name' => 'John Doe',
            'customer_email' => 'john.doe@example.com',
            'customer_phone' => '+1-555-0199',
            'status' => OrderStatus::CONFIRMED,
            'total_amount' => 149.98,
            'currency' => 'USD',
            'notes' => 'Priority shipping requested.',
        ]);

        $confirmedOrder->items()->createMany([
            [
                'product_name' => 'Mechanical Wireless Keyboard',
                'quantity' => 1,
                'unit_price' => 99.99,
                'subtotal' => 99.99,
            ],
            [
                'product_name' => 'Ergonomic Gaming Mouse',
                'quantity' => 1,
                'unit_price' => 49.99,
                'subtotal' => 49.99,
            ],
        ]);

        Payment::create([
            'order_id' => $confirmedOrder->id,
            'transaction_id' => 'cc_tx_sample_'.Str::random(10),
            'payment_method' => PaymentMethod::CREDIT_CARD->value,
            'amount' => 149.98,
            'currency' => 'USD',
            'status' => PaymentStatus::SUCCESSFUL,
            'idempotency_key' => Str::uuid()->toString(),
            'metadata' => [
                'gateway' => 'credit_card',
                'message' => 'Credit card charged successfully.',
            ],
        ]);

        // B) Pending Order (without payments, eligible for deletion and updates)
        $pendingOrder = Order::create([
            'user_id' => $customer->id,
            'customer_name' => 'Jane Smith',
            'customer_email' => 'jane.smith@example.com',
            'customer_phone' => '+1-555-0144',
            'status' => OrderStatus::PENDING,
            'total_amount' => 79.50,
            'currency' => 'USD',
            'notes' => 'Awaiting confirmation before payment.',
        ]);

        $pendingOrder->items()->create([
            'product_name' => 'USB-C Fast Charging Hub',
            'quantity' => 1,
            'unit_price' => 79.50,
            'subtotal' => 79.50,
        ]);

        // C) Cancelled Order
        $cancelledOrder = Order::create([
            'user_id' => $customer->id,
            'customer_name' => 'Robert Johnson',
            'customer_email' => 'robert.j@example.com',
            'status' => OrderStatus::CANCELLED,
            'total_amount' => 220.00,
            'currency' => 'USD',
            'notes' => 'Customer requested cancellation.',
        ]);

        $cancelledOrder->items()->create([
            'product_name' => 'Noise Cancelling Headphones',
            'quantity' => 1,
            'unit_price' => 220.00,
            'subtotal' => 220.00,
        ]);
    }
}
