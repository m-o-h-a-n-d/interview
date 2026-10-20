<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\CreateOrderDTO;
use App\DTOs\UpdateOrderDTO;
use App\Exceptions\OrderHasPaymentsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\V1\OrderCollection;
use App\Http\Resources\V1\OrderResource;
use App\Services\OrderService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponseTrait;

    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * View Orders: Retrieve all orders or filter by status (pending, confirmed, cancelled).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];

        // Non-admin users only view their own orders unless an admin request
        $user = auth('api')->user();
        if ($user && ! $user->isAdmin()) {
            $filters['user_id'] = $user->id;
        }

        $perPage = (int) $request->query('per_page', 15);
        $paginatedOrders = $this->orderService->listOrders($filters, $perPage);

        return response()->json(new OrderCollection($paginatedOrders));
    }

    /**
     * Create Order: Accept user details, purchased items, and calculate the total.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $userId = (int) $request->user('api')->id;
        $dto = CreateOrderDTO::fromRequest($request->validated(), $userId);

        $order = $this->orderService->createOrder($dto);

        return $this->successResponse(
            new OrderResource($order),
            'Order created successfully.',
            201
        );
    }

    /**
     * View single order details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderById($id);

            return $this->successResponse(
                new OrderResource($order),
                'Order details retrieved successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Order with ID {$id} not found.", 404);
        }
    }

    /**
     * Update Order: Modify existing order details.
     * Updates DB, evicts cache, and refreshes cache with new data.
     */
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        try {
            $dto = UpdateOrderDTO::fromRequest($request->validated());
            $updatedOrder = $this->orderService->updateOrder($id, $dto);

            return $this->successResponse(
                new OrderResource($updatedOrder),
                'Order updated and cache refreshed successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Order with ID {$id} not found.", 404);
        }
    }

    /**
     * Delete Order: Delete an order (only if no payments are associated).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->orderService->deleteOrder($id);

            return $this->successResponse(null, 'Order deleted successfully.', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Order with ID {$id} not found.", 404);
        } catch (OrderHasPaymentsException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
