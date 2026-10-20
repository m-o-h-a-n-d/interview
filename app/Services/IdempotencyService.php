<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflictException;
use Closure;
use Illuminate\Support\Facades\Cache;

class IdempotencyService
{
    protected const LOCK_TTL = 15; // 15 seconds mutex
    protected const CACHE_TTL = 86400; // 24 hours response cache

    /**
     * Execute an action with idempotency protection.
     *
     * @template T
     * @param string|null $idempotencyKey
     * @param Closure(): T $action
     * @return T
     * @throws IdempotencyConflictException
     */
    public function execute(?string $idempotencyKey, Closure $action): mixed
    {
        // If no idempotency key is supplied, proceed directly
        if (empty($idempotencyKey)) {
            return $action();
        }

        $responseKey = "idempotency:response:{$idempotencyKey}";
        $lockKey = "idempotency:lock:{$idempotencyKey}";

        // 1. If response is already cached from previous execution, return it immediately
        if ($cachedResponse = Cache::get($responseKey)) {
            return $cachedResponse;
        }

        // 2. Acquire atomic lock for in-flight requests
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        if (! $lock->get()) {
            throw new IdempotencyConflictException();
        }

        try {
            // Double-check cached response after acquiring lock
            if ($cachedResponse = Cache::get($responseKey)) {
                return $cachedResponse;
            }

            $result = $action();

            // Cache the result for future identical requests
            Cache::put($responseKey, $result, self::CACHE_TTL);

            return $result;
        } finally {
            optional($lock)->release();
        }
    }
}
