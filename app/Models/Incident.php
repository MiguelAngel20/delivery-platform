<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property int|null $driver_id
 * @property int|null $business_id
 * @property int|null $reported_by_user_id
 * @property int|null $resolved_by_user_id
 * @property IncidentType $type
 * @property IncidentSeverity $severity
 * @property IncidentStatus $status
 * @property string $description
 * @property string|null $resolution
 * @property string|null $idempotency_key
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'order_id',
    'customer_id',
    'driver_id',
    'business_id',
    'reported_by_user_id',
    'resolved_by_user_id',
    'type',
    'severity',
    'status',
    'description',
    'resolution',
    'idempotency_key',
    'resolved_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
