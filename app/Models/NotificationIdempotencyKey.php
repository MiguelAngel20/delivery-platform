<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persistent at-most-once claim for critical notification / mail side effects.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property string $status claimed|sent|failed
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'idempotency_key',
    'status',
    'attempts',
    'last_error',
    'completed_at',
])]
class NotificationIdempotencyKey extends Model
{
    public const STATUS_CLAIMED = 'claimed';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'completed_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
