<?php

namespace App\Models;

use App\Support\ProductImageStorage;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $product_category_id
 * @property string $name
 * @property string|null $description
 * @property string|null $image_path
 * @property bool $is_available
 * @property bool $is_active
 * @property bool $allow_special_instructions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductPrice|null $currentPrice
 */
#[Fillable([
    'branch_id',
    'product_category_id',
    'name',
    'description',
    'image_path',
    'is_available',
    'is_active',
    'allow_special_instructions',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_available' => true,
        'is_active' => true,
        'allow_special_instructions' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'allow_special_instructions' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(ProductPrice::class)
            ->where('is_active', true)
            ->latestOfMany('valid_from');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function promotionItems(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }

    public function imageUrl(): ?string
    {
        return app(ProductImageStorage::class)->url($this->image_path);
    }

    public function listPrice(): ?string
    {
        return $this->currentPrice?->list_price;
    }
}
