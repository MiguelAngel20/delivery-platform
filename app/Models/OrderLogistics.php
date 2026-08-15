<?php

namespace App\Models;

use App\Enums\DistanceMethod;
use Database\Factories\OrderLogisticsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $pickup_to_delivery_distance_meters
 * @property int|null $estimated_delivery_duration_seconds
 * @property DistanceMethod|null $distance_method
 * @property int|null $coverage_zone_id
 * @property string|null $coverage_zone_name
 * @property string|null $coverage_zone_type
 * @property int|null $coverage_radius_meters
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id',
    'pickup_to_delivery_distance_meters',
    'estimated_delivery_duration_seconds',
    'distance_method',
    'coverage_zone_id',
    'coverage_zone_name',
    'coverage_zone_type',
    'coverage_radius_meters',
])]
class OrderLogistics extends Model
{
    /** @use HasFactory<OrderLogisticsFactory> */
    use HasFactory;

    protected $table = 'order_logistics';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pickup_to_delivery_distance_meters' => 'integer',
            'estimated_delivery_duration_seconds' => 'integer',
            'distance_method' => DistanceMethod::class,
            'coverage_radius_meters' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function coverageZone(): BelongsTo
    {
        return $this->belongsTo(CoverageZone::class);
    }
}
