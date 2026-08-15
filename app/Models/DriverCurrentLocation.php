<?php

namespace App\Models;

use Database\Factories\DriverCurrentLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $driver_id
 * @property string $latitude
 * @property string $longitude
 * @property int|null $accuracy_meters
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'driver_id',
    'latitude',
    'longitude',
    'accuracy_meters',
    'recorded_at',
])]
class DriverCurrentLocation extends Model
{
    /** @use HasFactory<DriverCurrentLocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accuracy_meters' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
