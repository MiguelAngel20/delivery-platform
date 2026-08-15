<?php

namespace App\Models;

use App\Enums\OptionSelectionAction;
use App\Enums\ProductOptionGroupType;
use Database\Factories\OrderItemOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int|null $product_option_id
 * @property string $option_name
 * @property ProductOptionGroupType $option_type
 * @property string $price_modifier
 * @property OptionSelectionAction|null $selection_action
 * @property Carbon|null $created_at
 */
#[Fillable([
    'order_item_id',
    'product_option_id',
    'option_name',
    'option_type',
    'price_modifier',
    'selection_action',
    'created_at',
])]
class OrderItemOption extends Model
{
    /** @use HasFactory<OrderItemOptionFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_type' => ProductOptionGroupType::class,
            'price_modifier' => 'decimal:2',
            'selection_action' => OptionSelectionAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }
}
