<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\OrderItem;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderRepository implements OrderRepositoryInterface
{
    public function findById(int $id, array $with = ['items', 'payments', 'user']): ?Order
    {
        return Order::with($with)->find($id);
    }

    public function paginate(array $filters = [], int $perPage = 15, array $with = ['items', 'payments']): LengthAwarePaginator
    {
        $query = Order::with($with);

        if (! empty($filters['status'])) {
            $query->filterByStatus($filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function createWithItems(array $orderData, array $itemsData): Order
    {
        return DB::transaction(function () use ($orderData, $itemsData) {
            $order = Order::create($orderData);

            $totalAmount = 0.0;
            foreach ($itemsData as $item) {
                $subtotal = round($item['quantity'] * $item['unit_price'], 2);
                $totalAmount += $subtotal;

                $order->items()->create([
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total_amount' => round($totalAmount, 2)]);

            return $order->load(['items', 'user']);
        });
    }

    public function update(Order $order, array $orderData, ?array $itemsData = null): Order
    {
        return DB::transaction(function () use ($order, $orderData, $itemsData) {
            $order->update($orderData);

            if ($itemsData !== null) {
                $order->items()->delete();

                $totalAmount = 0.0;
                foreach ($itemsData as $item) {
                    $subtotal = round($item['quantity'] * $item['unit_price'], 2);
                    $totalAmount += $subtotal;

                    $order->items()->create([
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'subtotal' => $subtotal,
                    ]);
                }

                $order->update(['total_amount' => round($totalAmount, 2)]);
            }

            return $order->load(['items', 'payments', 'user']);
        });
    }

    public function delete(Order $order): bool
    {
        return (bool) $order->delete();
    }
}
