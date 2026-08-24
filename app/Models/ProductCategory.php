<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductCategory|null $parent
 * @property-read Collection<int, ProductCategory> $children
 */
#[Fillable([
    'branch_id',
    'parent_id',
    'name',
    'description',
    'sort_order',
    'is_active',
])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'parent_id' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @param  Builder<ProductCategory>  $query
     * @return Builder<ProductCategory>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }

    public function displayPath(): string
    {
        if ($this->parent !== null) {
            return $this->parent->name.' › '.$this->name;
        }

        return $this->name;
    }

    /**
     * Root kitchen/section name for tickets (parent when subcategory, else self).
     */
    public function rootName(): string
    {
        return $this->parent?->name ?? $this->name;
    }
}
