<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use App\Support\PromotionImageStorage;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $name
 * @property string|null $description
 * @property string $promotion_price
 * @property string|null $image_path
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property PromotionStatus $status
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'branch_id',
    'name',
    'description',
    'promotion_price',
    'image_path',
    'starts_at',
    'ends_at',
    'status',
    'created_by_user_id',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PromotionStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'promotion_price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => PromotionStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function imageUrl(): ?string
    {
        return app(PromotionImageStorage::class)->url($this->image_path);
    }
}
