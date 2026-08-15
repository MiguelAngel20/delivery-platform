<?php

namespace App\Models;

use App\Enums\CoverageScopeType;
use App\Enums\CoverageZoneType;
use Database\Factories\CoverageZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property CoverageScopeType $scope_type
 * @property int|null $scope_id
 * @property CoverageZoneType $zone_type
 * @property string|null $center_latitude
 * @property string|null $center_longitude
 * @property int|null $radius_meters
 * @property array<int, array{lat: float, lng: float}>|null $polygon
 * @property bool $is_active
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'scope_type',
    'scope_id',
    'zone_type',
    'center_latitude',
    'center_longitude',
    'radius_meters',
    'polygon',
    'is_active',
    'created_by_user_id',
])]
class CoverageZone extends Model
{
    /** @use HasFactory<CoverageZoneFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'zone_type' => 'radius',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => CoverageScopeType::class,
            'zone_type' => CoverageZoneType::class,
            'polygon' => 'array',
            'is_active' => 'boolean',
            'radius_meters' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
