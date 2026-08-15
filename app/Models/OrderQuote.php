<?php

namespace App\Models;

use App\Enums\OrderQuoteStatus;
use App\Enums\OrderQuoteType;
use Database\Factories\OrderQuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $custom_order_request_id
 * @property int|null $created_by_user_id
 * @property OrderQuoteType $type
 * @property string $subtotal
 * @property string $service_fee
 * @property string $discount_amount
 * @property string $total
 * @property OrderQuoteStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 */
#[Fillable([
    'order_id',
    'custom_order_request_id',
    'created_by_user_id',
    'type',
    'subtotal',
    'service_fee',
    'discount_amount',
    'total',
    'status',
    'expires_at',
    'accepted_at',
    'rejected_at',
])]
class OrderQuote extends Model
{
    /** @use HasFactory<OrderQuoteFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount_amount' => 0,
        'status' => 'pending',
        'type' => 'custom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderQuoteType::class,
            'status' => OrderQuoteStatus::class,
            'subtotal' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customOrderRequest(): BelongsTo
    {
        return $this->belongsTo(CustomOrderRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderQuoteItem::class, 'quote_id');
    }
}
