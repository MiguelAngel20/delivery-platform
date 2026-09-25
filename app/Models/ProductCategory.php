<?php

namespace App\Models;

use App\Support\CategorySchedule;
use Carbon\CarbonInterface;
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
 * @property bool $has_schedule
 * @property list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null $schedule_hours
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
    'has_schedule',
    'schedule_hours',
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
        'has_schedule' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'has_schedule' => 'boolean',
            'schedule_hours' => 'array',
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
     * A category is in use when it has products, or (for roots) when it has subcategories.
     */
    public function isInUse(): bool
    {
        if ($this->relationLoaded('products')) {
            if ($this->products->isNotEmpty()) {
                return true;
            }
        } elseif (isset($this->products_count)) {
            if ((int) $this->products_count > 0) {
                return true;
            }
        } elseif ($this->products()->exists()) {
            return true;
        }

        if (! $this->isRoot()) {
            return false;
        }

        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        if (isset($this->children_count)) {
            return (int) $this->children_count > 0;
        }

        return $this->children()->exists();
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

    /**
     * Principal category that owns the optional schedule (self or parent).
     */
    public function scheduleRoot(): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        $this->loadMissing('parent');

        return $this->parent ?? $this;
    }

    /**
     * Whether this category (and its products) should appear on the storefront now.
     * Subcategories inherit the principal category schedule.
     */
    public function isVisibleNow(?CarbonInterface $at = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $root = $this->scheduleRoot();

        if ($root->id !== $this->id && ! $root->is_active) {
            return false;
        }

        if (! $root->has_schedule) {
            return true;
        }

        return CategorySchedule::isWithinWeeklyHours(
            is_array($root->schedule_hours) ? $root->schedule_hours : null,
            $at,
        );
    }
}
