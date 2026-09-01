<?php

namespace App\Models;

use Database\Factories\PromotionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $promotion_id
 * @property int|null $product_id
 * @property string $name
 * @property string|null $description
 * @property string $quantity
 * @property string|null $original_price
 * @property bool $is_external_item
 * @property array<int, array<string, mixed>>|null $option_groups
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'promotion_id',
    'product_id',
    'name',
    'description',
    'quantity',
    'original_price',
    'is_external_item',
    'option_groups',
])]
class PromotionItem extends Model
{
    /** @use HasFactory<PromotionItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'is_external_item' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_external_item' => 'boolean',
            'option_groups' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
