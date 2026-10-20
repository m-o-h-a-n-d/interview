<?php

namespace App\Services;

use App\DTOs\CreateOrderDTO;
use App\DTOs\UpdateOrderDTO;
use App\Enums\OrderStatus;
use App\Exceptions\OrderHasPaymentsException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

class OrderService
{
    protected const CACHE_TTL = 3600; // 1 hour
    protected const DETAIL_CACHE_PREFIX = 'orders:detail:';
    protected const LIST_VERSION_KEY = 'orders:list_version';

    protected OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Get paginated orders list with filtering and caching.
     */
    public function listOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $version = (int) Cache::get(self::LIST_VERSION_KEY, 1);
        $filterHash = md5(json_encode($filters).":{$perPage}:".request('page', 1));
        $cacheKey = "orders:list:v{$version}:{$filterHash}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
            return $this->orderRepository->paginate($filters, $perPage);
        });
    }

    /**
     * Get single order by ID with cache-aside pattern.
     *
     * @throws ModelNotFoundException
     */
    public function getOrderById(int $id): Order
    {
        $cacheKey = self::DETAIL_CACHE_PREFIX.$id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            $order = $this->orderRepository->findById($id, ['items', 'payments', 'user']);

            if (! $order) {
                throw new ModelNotFoundException("Order with ID {$id} not found.");
            }

            return $order;
        });
    }

    /**
     * Create order with items and calculate total.
     */
    public function createOrder(CreateOrderDTO $dto): Order
    {
        $orderData = [
            'user_id' => $dto->userId,
            'customer_name' => $dto->customerName,
            'customer_email' => $dto->customerEmail,
            'customer_phone' => $dto->customerPhone,
            'status' => OrderStatus::PENDING->value,
            'currency' => $dto->currency,
            'notes' => $dto->notes,
        ];

        $itemsData = array_map(fn ($item) => $item->toArray(), $dto->items);

        $order = $this->orderRepository->createWithItems($orderData, $itemsData);

        // Populate detail cache and invalidate list caches
        $this->storeInCache($order);
        $this->invalidateListCache();

        return $order;
    }

    /**
     * Update order details:
     * 1. Evicts old cache.
     * 2. Updates database.
     * 3. Fetches fresh data and stores in cache (Write-through pattern).
     * 4. Invalidates list cache.
     *
     * @throws ModelNotFoundException
     */
    public function updateOrder(int $id, UpdateOrderDTO $dto): Order
    {
        $order = $this->orderRepository->findById($id, ['items', 'payments']);

        if (! $order) {
            throw new ModelNotFoundException("Order with ID {$id} not found.");
        }

        // 1. Evict existing cache entry
        $this->evictDetailCache($id);

        $orderData = array_filter([
            'customer_name' => $dto->customerName,
            'customer_email' => $dto->customerEmail,
            'customer_phone' => $dto->customerPhone,
            'status' => $dto->status,
            'notes' => $dto->notes,
        ], fn ($val) => $val !== null);

        $itemsData = $dto->items !== null ? array_map(fn ($item) => $item->toArray(), $dto->items) : null;

        // 2. Persist to database
        $updatedOrder = $this->orderRepository->update($order, $orderData, $itemsData);

        // 3. Cache the fresh data into cache (Write-through)
        $this->storeInCache($updatedOrder);

        // 4. Invalidate list caches
        $this->invalidateListCache();

        return $updatedOrder;
    }

    /**
     * Delete order (only if no payments are associated).
     *
     * @throws ModelNotFoundException|OrderHasPaymentsException
     */
    public function deleteOrder(int $id): bool
    {
        $order = $this->orderRepository->findById($id, ['payments']);

        if (! $order) {
            throw new ModelNotFoundException("Order with ID {$id} not found.");
        }

        // Business rule: Orders cannot be deleted if they have associated payments.
        if (! $order->canBeDeleted()) {
            throw new OrderHasPaymentsException("Orders cannot be deleted if they have associated payments.");
        }

        $deleted = $this->orderRepository->delete($order);

        // Evict detail cache and invalidate lists
        $this->evictDetailCache($id);
        $this->invalidateListCache();

        return $deleted;
    }

    /**
     * Store fresh order in detail cache.
     */
    public function storeInCache(Order $order): void
    {
        Cache::put(self::DETAIL_CACHE_PREFIX.$order->id, $order, self::CACHE_TTL);
    }

    /**
     * Evict single order from detail cache.
     */
    public function evictDetailCache(int $id): void
    {
        Cache::forget(self::DETAIL_CACHE_PREFIX.$id);
    }

    /**
     * Invalidate all cached order lists by bumping version key.
     */
    public function invalidateListCache(): void
    {
        if (Cache::has(self::LIST_VERSION_KEY)) {
            Cache::increment(self::LIST_VERSION_KEY);
        } else {
            Cache::put(self::LIST_VERSION_KEY, 2, self::CACHE_TTL * 24);
        }
    }
}
