<?php

namespace App\Models;

use App\Enums\ProductOptionGroupType;
use Database\Factories\ProductOptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property ProductOptionGroupType $type
 * @property bool $is_required
 * @property int $min_selection
 * @property int $max_selection
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'name',
    'type',
    'is_required',
    'min_selection',
    'max_selection',
    'sort_order',
    'is_active',
])]
class ProductOptionGroup extends Model
{
    /** @use HasFactory<ProductOptionGroupFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_required' => false,
        'min_selection' => 0,
        'max_selection' => 1,
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductOptionGroupType::class,
            'is_required' => 'boolean',
            'min_selection' => 'integer',
            'max_selection' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'option_group_id')->orderBy('sort_order');
    }
}
