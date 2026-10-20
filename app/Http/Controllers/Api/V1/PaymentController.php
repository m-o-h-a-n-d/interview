<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\ProcessPaymentDTO;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ProcessPaymentRequest;
use App\Http\Resources\V1\PaymentCollection;
use App\Http\Resources\V1\PaymentResource;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    protected PaymentService $paymentService;
    protected OrderService $orderService;

    public function __construct(PaymentService $paymentService, OrderService $orderService)
    {
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
    }

    /**
     * View Payments: Retrieve all payments.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'order_id' => $request->query('order_id'),
            'status' => $request->query('status'),
            'payment_method' => $request->query('payment_method'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $payments = $this->paymentService->listPayments($filters, $perPage);

        return response()->json(new PaymentCollection($payments));
    }

    /**
     * Process Payment: Simulate payment processing for an order.
     */
    public function store(ProcessPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderId = (int) $validated['order_id'];

        try {
            $order = $this->orderService->getOrderById($orderId);
            $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? ($validated['idempotency_key'] ?? null);

            $dto = ProcessPaymentDTO::fromRequest(
                data: $validated,
                orderId: $orderId,
                orderAmount: (float) $order->total_amount,
                orderCurrency: (string) $order->currency,
                idempotencyKey: $idempotencyKey
            );

            $payment = $this->paymentService->processPayment($dto);

            return $this->successResponse(
                new PaymentResource($payment),
                'Payment processed successfully.',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Order with ID {$orderId} not found.", 404);
        } catch (InvalidOrderStatusException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (PaymentGatewayException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400, $e->getGatewayDetails(), 'PAYMENT_FAILED');
        }
    }

    /**
     * View Payments: Retrieve payment details for a specific order.
     */
    public function getOrderPayments(int $orderId, Request $request): JsonResponse
    {
        try {
            // Verify order exists
            $this->orderService->getOrderById($orderId);

            $perPage = (int) $request->query('per_page', 15);
            $payments = $this->paymentService->getPaymentsByOrderId($orderId, $perPage);

            return response()->json(new PaymentCollection($payments));
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Order with ID {$orderId} not found.", 404);
        }
    }

    /**
     * View single payment details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $payment = $this->paymentService->getPaymentById($id);

            return $this->successResponse(
                new PaymentResource($payment),
                'Payment details retrieved successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Payment with ID {$id} not found.", 404);
        }
    }
}
