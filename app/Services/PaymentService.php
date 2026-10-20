<?php

namespace App\Services;

use App\DTOs\ProcessPaymentDTO;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderStatusException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    protected PaymentRepositoryInterface $paymentRepository;
    protected OrderRepositoryInterface $orderRepository;
    protected PaymentGatewayManager $gatewayManager;
    protected IdempotencyService $idempotencyService;
    protected OrderService $orderService;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        OrderRepositoryInterface $orderRepository,
        PaymentGatewayManager $gatewayManager,
        IdempotencyService $idempotencyService,
        OrderService $orderService,
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->orderRepository = $orderRepository;
        $this->gatewayManager = $gatewayManager;
        $this->idempotencyService = $idempotencyService;
        $this->orderService = $orderService;
    }

    /**
     * Process payment for an order with idempotency protection and business rule checks.
     *
     * @throws ModelNotFoundException|InvalidOrderStatusException|PaymentGatewayException
     */
    public function processPayment(ProcessPaymentDTO $dto): Payment
    {
        return $this->idempotencyService->execute($dto->idempotencyKey, function () use ($dto) {
            // Check if payment already exists for this idempotency key in database
            if ($dto->idempotencyKey) {
                $existingPayment = $this->paymentRepository->findByIdempotencyKey($dto->idempotencyKey);
                if ($existingPayment) {
                    return $existingPayment;
                }
            }

            // 1. Fetch Order and validate existence
            $order = $this->orderRepository->findById($dto->orderId);
            if (! $order) {
                throw new ModelNotFoundException("Order with ID {$dto->orderId} not found.");
            }

            // 2. Validate Business Rule: Payments can ONLY be processed for orders in confirmed status.
            if (! $order->canAcceptPayment()) {
                throw new InvalidOrderStatusException(
                    "Payments can only be processed for orders in the confirmed status. Current status is '{$order->status->value}'."
                );
            }

            // 3. Resolve the appropriate Gateway Strategy via Manager
            $gateway = $this->gatewayManager->gateway($dto->paymentMethod);

            // 4. Trigger the gateway transaction
            $gatewayResult = $gateway->process($dto);

            // 5. Persist Payment Record in Database
            $payment = DB::transaction(function () use ($order, $dto, $gatewayResult) {
                $payment = $this->paymentRepository->create([
                    'order_id' => $order->id,
                    'transaction_id' => $gatewayResult->transactionId,
                    'payment_method' => $dto->paymentMethod,
                    'amount' => $dto->amount,
                    'currency' => $dto->currency,
                    'status' => $gatewayResult->status->value,
                    'idempotency_key' => $dto->idempotencyKey,
                    'metadata' => [
                        'gateway' => $gatewayResult->gatewayName,
                        'message' => $gatewayResult->message,
                        'raw_response' => $gatewayResult->rawResponse,
                    ],
                    'error_message' => $gatewayResult->isSuccess ? null : $gatewayResult->message,
                ]);

                // Invalidate the order detail cache so relations reflect the new payment
                $this->orderService->evictDetailCache($order->id);

                return $payment;
            });

            // 6. If gateway processing failed, throw PaymentGatewayException so API returns appropriate 400 response
            if (! $gatewayResult->isSuccess) {
                throw new PaymentGatewayException(
                    $gatewayResult->message ?? 'Payment transaction failed.',
                    400,
                    [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'status' => $payment->status->value,
                    ]
                );
            }

            return $payment->load('order');
        });
    }

    /**
     * Get paginated payments list.
     */
    public function listPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->paginate($filters, $perPage);
    }

    /**
     * Get payments for a specific order.
     */
    public function getPaymentsByOrderId(int $orderId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->paginate(['order_id' => $orderId], $perPage);
    }

    /**
     * Get single payment by ID.
     */
    public function getPaymentById(int $id): Payment
    {
        $payment = $this->paymentRepository->findById($id);

        if (! $payment) {
            throw new ModelNotFoundException("Payment with ID {$id} not found.");
        }

        return $payment;
    }
}
