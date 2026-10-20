<?php

namespace App\Http\Resources\V1;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'transaction_id' => $this->transaction_id,
            'payment_method' => $this->payment_method,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status instanceof PaymentStatus ? $this->status->value : $this->status,
            'idempotency_key' => $this->idempotency_key,
            'metadata' => $this->metadata,
            'error_message' => $this->error_message,
            'order' => new OrderResource($this->whenLoaded('order')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
