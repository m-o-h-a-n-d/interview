<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function findById(int $id, array $with = ['order']): ?Payment
    {
        return Payment::with($with)->find($id);
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::with('order')->where('transaction_id', $transactionId)->first();
    }

    public function findByIdempotencyKey(string $key): ?Payment
    {
        return Payment::with('order')->where('idempotency_key', $key)->first();
    }

    public function paginate(array $filters = [], int $perPage = 15, array $with = ['order']): LengthAwarePaginator
    {
        $query = Payment::with($with);

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }
}
