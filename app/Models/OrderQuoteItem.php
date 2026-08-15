<?php

namespace App\Models;

use Database\Factories\OrderQuoteItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quote_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string|null $acquisition_cost
 * @property string|null $notes
 */
#[Fillable([
    'quote_id',
    'description',
    'quantity',
    'unit_price',
    'subtotal',
    'acquisition_cost',
    'notes',
])]
class OrderQuoteItem extends Model
{
    /** @use HasFactory<OrderQuoteItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(OrderQuote::class, 'quote_id');
    }
}
