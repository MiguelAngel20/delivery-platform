<?php

namespace App\Models;

use App\Enums\CancellationReasonCode;
use App\Enums\CancellationResponsibility;
use App\Enums\CancellationReviewStatus;
use App\Enums\CancelledByType;
use App\Enums\OrderStatus;
use Database\Factories\OrderCancellationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $cancelled_by_user_id
 * @property CancelledByType $cancelled_by_type
 * @property CancellationReasonCode $reason_code
 * @property string|null $reason
 * @property OrderStatus $previous_order_status
 * @property CancellationResponsibility $responsibility
 * @property CancellationReviewStatus $review_status
 * @property int|null $reviewed_by_user_id
 * @property string|null $review_notes
 * @property Carbon $cancelled_at
 * @property Carbon|null $reviewed_at
 */
#[Fillable([
    'order_id',
    'cancelled_by_user_id',
    'cancelled_by_type',
    'reason_code',
    'reason',
    'previous_order_status',
    'responsibility',
    'review_status',
    'reviewed_by_user_id',
    'review_notes',
    'cancelled_at',
    'reviewed_at',
])]
class OrderCancellation extends Model
{
    /** @use HasFactory<OrderCancellationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_by_type' => CancelledByType::class,
            'reason_code' => CancellationReasonCode::class,
            'previous_order_status' => OrderStatus::class,
            'responsibility' => CancellationResponsibility::class,
            'review_status' => CancellationReviewStatus::class,
            'cancelled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
