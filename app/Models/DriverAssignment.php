<?php

namespace App\Models;

use App\Enums\DriverAssignmentStatus;
use Database\Factories\DriverAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $driver_id
 * @property DriverAssignmentStatus $status
 * @property Carbon|null $offered_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'order_id',
    'driver_id',
    'status',
    'offered_at',
    'accepted_at',
    'rejected_at',
    'expired_at',
    'cancelled_at',
])]
class DriverAssignment extends Model
{
    /** @use HasFactory<DriverAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DriverAssignmentStatus::class,
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
}
