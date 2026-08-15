<?php

namespace App\Models;

use App\Enums\UpgradeRequestStatus;
use App\Enums\UpgradeRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $requested_by_user_id
 * @property UpgradeRequestType $type
 * @property int $requested_quantity
 * @property int|null $branch_id
 * @property UpgradeRequestStatus $status
 * @property string|null $notes
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'business_id',
    'requested_by_user_id',
    'type',
    'requested_quantity',
    'branch_id',
    'status',
    'notes',
    'reviewed_by_user_id',
    'reviewed_at',
])]
class BusinessUpgradeRequest extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UpgradeRequestType::class,
            'status' => UpgradeRequestStatus::class,
            'requested_quantity' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
