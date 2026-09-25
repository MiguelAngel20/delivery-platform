<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use App\Support\PromotionImageStorage;
use App\Support\PromotionSchedule;
use Carbon\CarbonInterface;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $is_recurring
 * @property Carbon|null $recurrence_starts_on
 * @property Carbon|null $recurrence_ends_on
 * @property list<array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null $recurring_hours
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
    'is_recurring',
    'recurrence_starts_on',
    'recurrence_ends_on',
    'recurring_hours',
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
        'is_recurring' => false,
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
            'is_recurring' => 'boolean',
            'recurrence_starts_on' => 'date',
            'recurrence_ends_on' => 'date',
            'recurring_hours' => 'array',
            'status' => PromotionStatus::class,
        ];
    }

    /**
     * Active promotions inside their one-shot or recurrence date window.
     * Weekly hours for recurring promos are enforced in isCurrentlyAvailable().
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentlyAvailable(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();
        $date = $at->toDateString();

        return $query
            ->where('status', PromotionStatus::Active)
            ->where(function (Builder $outer) use ($at, $date): void {
                $outer
                    ->where(function (Builder $oneShot) use ($at): void {
                        $oneShot
                            ->where('is_recurring', false)
                            ->where(function (Builder $inner) use ($at): void {
                                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
                            })
                            ->where(function (Builder $inner) use ($at): void {
                                $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
                            });
                    })
                    ->orWhere(function (Builder $recurring) use ($date): void {
                        $recurring
                            ->where('is_recurring', true)
                            ->whereDate('recurrence_starts_on', '<=', $date)
                            ->where(function (Builder $inner) use ($date): void {
                                $inner
                                    ->whereNull('recurrence_ends_on')
                                    ->orWhereDate('recurrence_ends_on', '>=', $date);
                            });
                    });
            });
    }

    /**
     * Whether this promotion can be shown or ordered right now
     * (includes weekly hours for recurring promotions).
     */
    public function isCurrentlyAvailable(?CarbonInterface $at = null): bool
    {
        return PromotionSchedule::isAvailable($this, $at);
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
