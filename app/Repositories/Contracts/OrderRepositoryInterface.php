<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Order;

    public function paginate(array $filters = [], int $perPage = 15, array $with = []): LengthAwarePaginator;

    public function createWithItems(array $orderData, array $itemsData): Order;

    public function update(Order $order, array $orderData, ?array $itemsData = null): Order;

    public function delete(Order $order): bool;
}
