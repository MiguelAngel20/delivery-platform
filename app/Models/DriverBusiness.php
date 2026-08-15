<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property int $business_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'driver_id',
    'business_id',
])]
class DriverBusiness extends Pivot
{
    public $incrementing = true;

    protected $table = 'driver_businesses';

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
