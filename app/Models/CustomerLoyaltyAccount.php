<?php

namespace App\Models;

use App\Enums\LoyaltyRewardType;
use Database\Factories\CustomerLoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $qualifying_orders_count
 * @property string|null $pending_reward_amount
 * @property string|null $pending_reward_calculator
 * @property int|null $reserved_order_id
 * @property LoyaltyRewardType|null $reserved_reward_type
 * @property string|null $reserved_reward_amount
 * @property Carbon|null $launch_consumed_at
 * @property int|null $launch_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'customer_id',
    'qualifying_orders_count',
    'pending_reward_amount',
    'pending_reward_calculator',
    'reserved_order_id',
    'reserved_reward_type',
    'reserved_reward_amount',
    'launch_consumed_at',
    'launch_order_id',
])]
class CustomerLoyaltyAccount extends Model
{
    /** @use HasFactory<CustomerLoyaltyAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qualifying_orders_count' => 'integer',
            'pending_reward_amount' => 'decimal:2',
            'reserved_reward_amount' => 'decimal:2',
            'reserved_reward_type' => LoyaltyRewardType::class,
            'launch_consumed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reserved_order_id');
    }

    public function launchOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'launch_order_id');
    }

    public function hasPendingReward(): bool
    {
        return $this->pending_reward_amount !== null
            && bccomp((string) $this->pending_reward_amount, '0', 2) === 1
            && $this->reserved_order_id === null;
    }
}
