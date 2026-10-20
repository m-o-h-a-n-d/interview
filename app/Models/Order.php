<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'total_amount',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Check if the order has any associated payments.
     */
    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    /**
     * Business rule: Orders cannot be deleted if they have associated payments.
     */
    public function canBeDeleted(): bool
    {
        return ! $this->hasPayments();
    }

    /**
     * Business rule: Payments can only be processed for orders in the confirmed status.
     */
    public function canAcceptPayment(): bool
    {
        return $this->status === OrderStatus::CONFIRMED;
    }

    /**
     * Scope for filtering orders by status (pending, confirmed, cancelled).
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
