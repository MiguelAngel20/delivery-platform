<?php

namespace App\Models;

use Database\Factories\DeliveryTripOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delivery_trip_id
 * @property int $order_id
 * @property int|null $sequence
 */
#[Fillable([
    'delivery_trip_id',
    'order_id',
    'sequence',
])]
class DeliveryTripOrder extends Model
{
    /** @use HasFactory<DeliveryTripOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(DeliveryTrip::class, 'delivery_trip_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
