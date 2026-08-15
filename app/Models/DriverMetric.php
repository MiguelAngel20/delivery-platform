<?php

namespace App\Models;

use Database\Factories\DriverMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property int $offered_orders
 * @property int $accepted_orders
 * @property int $rejected_orders
 * @property int $completed_orders
 * @property int $cancelled_orders
 * @property int $responsible_cancellations
 * @property int $incident_count
 * @property int $responsible_incidents
 * @property string|null $average_rating
 * @property int $total_ratings
 * @property string $trust_score
 * @property Carbon|null $last_recalculated_at
 */
#[Fillable([
    'driver_id',
    'offered_orders',
    'accepted_orders',
    'rejected_orders',
    'completed_orders',
    'cancelled_orders',
    'responsible_cancellations',
    'incident_count',
    'responsible_incidents',
    'average_rating',
    'total_ratings',
    'trust_score',
    'last_recalculated_at',
])]
class DriverMetric extends Model
{
    /** @use HasFactory<DriverMetricFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'offered_orders' => 0,
        'accepted_orders' => 0,
        'rejected_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'responsible_cancellations' => 0,
        'incident_count' => 0,
        'responsible_incidents' => 0,
        'total_ratings' => 0,
        'trust_score' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered_orders' => 'integer',
            'accepted_orders' => 'integer',
            'rejected_orders' => 'integer',
            'completed_orders' => 'integer',
            'cancelled_orders' => 'integer',
            'responsible_cancellations' => 'integer',
            'incident_count' => 'integer',
            'responsible_incidents' => 'integer',
            'average_rating' => 'decimal:2',
            'total_ratings' => 'integer',
            'trust_score' => 'decimal:2',
            'last_recalculated_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function requiresReview(): bool
    {
        return (float) $this->trust_score <= (float) config('reputation.driver.requires_review_max_score', 40);
    }
}
