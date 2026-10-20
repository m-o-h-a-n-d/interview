<?php

namespace App\Http\Resources\V1;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'status' => $this->status instanceof OrderStatus ? $this->status->value : $this->status,
            'total_amount' => (float) $this->total_amount,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'has_payments' => $this->hasPayments(),
            'can_be_deleted' => $this->canBeDeleted(),
            'can_accept_payment' => $this->canAcceptPayment(),
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
