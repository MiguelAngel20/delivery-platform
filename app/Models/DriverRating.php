<?php

namespace App\Models;

use Database\Factories\DriverRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $driver_id
 * @property int $customer_id
 * @property int $overall_rating
 * @property int|null $speed_rating
 * @property int|null $service_rating
 * @property int|null $care_rating
 * @property int|null $respect_rating
 * @property int|null $communication_rating
 * @property string|null $comment
 */
#[Fillable([
    'order_id',
    'driver_id',
    'customer_id',
    'overall_rating',
    'speed_rating',
    'service_rating',
    'care_rating',
    'respect_rating',
    'communication_rating',
    'comment',
])]
class DriverRating extends Model
{
    /** @use HasFactory<DriverRatingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'speed_rating' => 'integer',
            'service_rating' => 'integer',
            'care_rating' => 'integer',
            'respect_rating' => 'integer',
            'communication_rating' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
