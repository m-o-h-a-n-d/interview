<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function findById(int $id, array $with = []): ?Payment;

    public function findByTransactionId(string $transactionId): ?Payment;

    public function findByIdempotencyKey(string $key): ?Payment;

    public function paginate(array $filters = [], int $perPage = 15, array $with = []): LengthAwarePaginator;

    public function create(array $data): Payment;
}
