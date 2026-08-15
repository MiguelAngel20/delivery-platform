<?php

namespace App\Models;

use App\Enums\DeliveryTripStatus;
use Database\Factories\DeliveryTripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property int $business_id
 * @property int $branch_id
 * @property DeliveryTripStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'driver_id',
    'business_id',
    'branch_id',
    'status',
    'started_at',
    'completed_at',
])]
class DeliveryTrip extends Model
{
    /** @use HasFactory<DeliveryTripFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryTripStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function tripOrders(): HasMany
    {
        return $this->hasMany(DeliveryTripOrder::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_trip_orders')
            ->withPivot(['sequence'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }
}
