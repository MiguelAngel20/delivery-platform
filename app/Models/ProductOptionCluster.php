<?php

namespace App\Models;

use Database\Factories\ProductOptionClusterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $option_group_id
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductOptionGroup|null $group
 * @property-read Collection<int, ProductOption> $options
 */
#[Fillable([
    'option_group_id',
    'name',
    'sort_order',
])]
class ProductOptionCluster extends Model
{
    /** @use HasFactory<ProductOptionClusterFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'option_group_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'option_cluster_id')->orderBy('sort_order');
    }
}
