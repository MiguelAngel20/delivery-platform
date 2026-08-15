<?php

namespace App\Models;

use App\Enums\CustomerTrustLevel;
use Database\Factories\CustomerMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $total_orders
 * @property int $completed_orders
 * @property int $cancelled_orders
 * @property int $late_cancellations
 * @property int $rejected_at_delivery
 * @property int $payment_incidents
 * @property int $incident_count
 * @property int $responsible_incidents
 * @property string $trust_score
 * @property CustomerTrustLevel $trust_level
 * @property Carbon|null $last_recalculated_at
 */
#[Fillable([
    'customer_id',
    'total_orders',
    'completed_orders',
    'cancelled_orders',
    'late_cancellations',
    'rejected_at_delivery',
    'payment_incidents',
    'incident_count',
    'responsible_incidents',
    'trust_score',
    'trust_level',
    'last_recalculated_at',
])]
class CustomerMetric extends Model
{
    /** @use HasFactory<CustomerMetricFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'late_cancellations' => 0,
        'rejected_at_delivery' => 0,
        'payment_incidents' => 0,
        'incident_count' => 0,
        'responsible_incidents' => 0,
        'trust_score' => 0,
        'trust_level' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_orders' => 'integer',
            'completed_orders' => 'integer',
            'cancelled_orders' => 'integer',
            'late_cancellations' => 'integer',
            'rejected_at_delivery' => 'integer',
            'payment_incidents' => 'integer',
            'incident_count' => 'integer',
            'responsible_incidents' => 'integer',
            'trust_score' => 'decimal:2',
            'trust_level' => CustomerTrustLevel::class,
            'last_recalculated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
