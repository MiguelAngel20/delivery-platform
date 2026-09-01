<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $promotion_id
 * @property string $product_name
 * @property string $quantity
 * @property string $unit_list_price
 * @property string $unit_discount
 * @property string $unit_final_price
 * @property string|null $unit_acquisition_cost
 * @property string $subtotal
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id',
    'product_id',
    'promotion_id',
    'product_name',
    'quantity',
    'unit_list_price',
    'unit_discount',
    'unit_final_price',
    'unit_acquisition_cost',
    'subtotal',
    'notes',
    'metadata',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_list_price' => 'decimal:2',
            'unit_discount' => 'decimal:2',
            'unit_final_price' => 'decimal:2',
            'unit_acquisition_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
