<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_fails_if_order_is_pending(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->create([
            'user_id' => $auth['user']->id,
            'status' => OrderStatus::PENDING,
            'total_amount' => 100.00,
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
        ];

        $response = $this->postJson(route('payments.store'), $payload, $auth['headers']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "Payments can only be processed for orders in the confirmed status. Current status is 'pending'.",
            ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_fails_if_order_is_cancelled(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->cancelled()->create([
            'user_id' => $auth['user']->id,
            'total_amount' => 50.00,
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
        ];

        $response = $this->postJson(route('payments.store'), $payload, $auth['headers']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => "Payments can only be processed for orders in the confirmed status. Current status is 'cancelled'.",
            ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_payment_succeeds_for_confirmed_order_via_credit_card(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create([
            'user_id' => $auth['user']->id,
            'total_amount' => 250.00,
            'currency' => 'USD',
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'gateway_details' => [
                'card_number' => '4242424242424242',
                'exp_month' => 12,
                'exp_year' => 2030,
                'cvv' => '123',
            ],
        ];

        $response = $this->postJson(route('payments.store'), $payload, $auth['headers']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'payment_method' => 'credit_card',
                    'amount' => 250.00,
                    'currency' => 'USD',
                    'status' => 'successful',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'amount' => 250.00,
            'status' => 'successful',
        ]);
    }

    public function test_payment_succeeds_for_confirmed_order_via_paypal(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create([
            'user_id' => $auth['user']->id,
            'total_amount' => 89.99,
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'gateway_details' => [
                'paypal_email' => 'buyer@example.com',
            ],
        ];

        $response = $this->postJson(route('payments.store'), $payload, $auth['headers']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'payment_method' => 'paypal',
                    'status' => 'successful',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
            'status' => 'successful',
        ]);
    }

    public function test_payment_failure_simulation(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create([
            'user_id' => $auth['user']->id,
            'total_amount' => 100.00,
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
            'gateway_details' => [
                'simulate_failure' => true,
            ],
        ];

        $response = $this->postJson(route('payments.store'), $payload, $auth['headers']);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error_code' => 'PAYMENT_FAILED',
            ]);

        // Failed payment record is stored in DB for auditing
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::FAILED->value,
        ]);
    }

    public function test_payment_idempotency_prevents_duplicate_processing(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create([
            'user_id' => $auth['user']->id,
            'total_amount' => 300.00,
        ]);

        $idempotencyKey = (string) Str::uuid();

        $headers = array_merge($auth['headers'], [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $payload = [
            'order_id' => $order->id,
            'payment_method' => 'credit_card',
        ];

        // First request
        $response1 = $this->postJson(route('payments.store'), $payload, $headers);
        $response1->assertStatus(201);
        $transactionId1 = $response1->json('data.transaction_id');

        // Second request with identical Idempotency-Key
        $response2 = $this->postJson(route('payments.store'), $payload, $headers);
        $response2->assertStatus(201);
        $transactionId2 = $response2->json('data.transaction_id');

        // Must return identical transaction without creating a duplicate record
        $this->assertEquals($transactionId1, $transactionId2);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_user_can_view_payments_for_specific_order(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create(['user_id' => $auth['user']->id]);

        Payment::factory()->count(2)->create([
            'order_id' => $order->id,
        ]);

        $response = $this->getJson(route('orders.payments', $order->id), $auth['headers']);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_retrieve_available_payment_gateways(): void
    {
        $auth = $this->authenticateUser();

        $response = $this->getJson(route('payments.gateways'), $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'default' => 'credit_card',
                ],
            ]);

        $gateways = $response->json('data.available_gateways');
        $this->assertContains('credit_card', $gateways);
        $this->assertContains('paypal', $gateways);
        $this->assertContains('stripe', $gateways);
    }
}
