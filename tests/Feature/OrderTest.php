<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_order_with_items_and_calculate_total(): void
    {
        $auth = $this->authenticateUser();

        $payload = [
            'customer_name' => 'Michael Scott',
            'customer_email' => 'michael@dundermifflin.com',
            'customer_phone' => '+1-555-0100',
            'currency' => 'USD',
            'notes' => 'Please deliver before 5 PM.',
            'items' => [
                [
                    'product_name' => 'Paper Ream A4',
                    'quantity' => 10,
                    'unit_price' => 5.50, // Subtotal: 55.00
                ],
                [
                    'product_name' => 'Desk Organizer',
                    'quantity' => 2,
                    'unit_price' => 22.50, // Subtotal: 45.00
                ],
            ],
        ];

        $response = $this->postJson(route('orders.store'), $payload, $auth['headers']);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_name' => 'Michael Scott',
                    'customer_email' => 'michael@dundermifflin.com',
                    'status' => 'pending',
                    'total_amount' => 100.00, // 55.00 + 45.00
                    'currency' => 'USD',
                    'has_payments' => false,
                    'can_be_deleted' => true,
                    'can_accept_payment' => false, // not confirmed yet
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Michael Scott',
            'total_amount' => 100.00,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_order_creation_fails_without_items(): void
    {
        $auth = $this->authenticateUser();

        $payload = [
            'customer_name' => 'Dwight Schrute',
            'customer_email' => 'dwight@beetfarm.com',
            'items' => [],
        ];

        $response = $this->postJson(route('orders.store'), $payload, $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_order_creation_validates_customer_fields_and_item_constraints(): void
    {
        $auth = $this->authenticateUser();

        $payload = [
            'customer_name' => 'A', // min 2
            'customer_email' => 'not-an-email',
            'items' => [
                [
                    'product_name' => '',
                    'quantity' => 0, // min 1
                    'unit_price' => -10, // min 0.01
                ],
            ],
        ];

        $response = $this->postJson(route('orders.store'), $payload, $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_name',
                'customer_email',
                'items.0.product_name',
                'items.0.quantity',
                'items.0.unit_price',
            ]);
    }

    public function test_order_update_validates_status_enum_and_types(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->create(['user_id' => $auth['user']->id]);

        $invalidPayload = [
            'status' => 'invalid_status_value',
            'customer_email' => 'invalid-email-address',
        ];

        $response = $this->putJson(route('orders.update', $order->id), $invalidPayload, $auth['headers']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'customer_email']);
    }

    public function test_user_can_view_orders_and_filter_by_status(): void
    {
        $auth = $this->authenticateUser();

        // Create 2 pending orders, 1 confirmed order
        Order::factory()->count(2)->create([
            'user_id' => $auth['user']->id,
            'status' => OrderStatus::PENDING,
        ]);

        Order::factory()->confirmed()->create([
            'user_id' => $auth['user']->id,
        ]);

        // Filter by confirmed
        $response = $this->getJson(route('orders.index', ['status' => 'confirmed']), $auth['headers']);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('confirmed', $data[0]['status']);

        // Filter by pending
        $pendingResponse = $this->getJson(route('orders.index', ['status' => 'pending']), $auth['headers']);
        $pendingResponse->assertStatus(200);
        $this->assertCount(2, $pendingResponse->json('data'));
    }

    public function test_user_can_view_single_order_details(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->getJson(route('orders.show', $order->id), $auth['headers']);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'customer_name' => $order->customer_name,
                ],
            ]);
    }

    public function test_order_update_invalidates_cache_and_stores_fresh_data(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->create([
            'user_id' => $auth['user']->id,
            'status' => OrderStatus::PENDING,
            'customer_name' => 'Initial Name',
        ]);

        // Prime cache by reading order
        $this->getJson(route('orders.show', $order->id), $auth['headers']);
        $cacheKey = "orders:detail:{$order->id}";
        $this->assertTrue(Cache::has($cacheKey));

        // Update the order (status to confirmed and new customer name)
        $updatePayload = [
            'customer_name' => 'Updated Senior Name',
            'status' => 'confirmed',
        ];

        $updateResponse = $this->putJson(route('orders.update', $order->id), $updatePayload, $auth['headers']);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'customer_name' => 'Updated Senior Name',
                    'status' => 'confirmed',
                    'can_accept_payment' => true,
                ],
            ]);

        // Verify write-through cache: fresh data is cached
        $this->assertTrue(Cache::has($cacheKey));
        $cachedOrder = Cache::get($cacheKey);
        $this->assertEquals('Updated Senior Name', $cachedOrder->customer_name);
        $this->assertEquals(OrderStatus::CONFIRMED, $cachedOrder->status);
    }

    public function test_user_can_delete_order_when_no_payments_exist(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->deleteJson(route('orders.destroy', $order->id), [], $auth['headers']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_order_cannot_be_deleted_if_it_has_associated_payments(): void
    {
        $auth = $this->authenticateUser();
        $order = Order::factory()->confirmed()->create(['user_id' => $auth['user']->id]);

        // Attach a payment to this order
        Payment::factory()->create([
            'order_id' => $order->id,
        ]);

        $this->assertTrue($order->hasPayments());

        $response = $this->deleteJson(route('orders.destroy', $order->id), [], $auth['headers']);

        // Business rule check: must fail with 422
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Orders cannot be deleted if they have associated payments.',
            ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_admin_can_view_all_orders_while_user_views_only_theirs(): void
    {
        $user1 = $this->authenticateUser();
        $user2 = User::factory()->create();

        Order::factory()->create(['user_id' => $user1['user']->id]);
        Order::factory()->create(['user_id' => $user2->id]);

        // User 1 only sees their 1 order
        $userResponse = $this->getJson(route('orders.index'), $user1['headers']);
        $userResponse->assertStatus(200);
        $this->assertCount(1, $userResponse->json('data'));

        // Admin sees all 2 orders
        $admin = $this->authenticateAdmin();
        $adminResponse = $this->getJson(route('orders.index'), $admin['headers']);
        $adminResponse->assertStatus(200);
        $this->assertCount(2, $adminResponse->json('data'));
    }
}
