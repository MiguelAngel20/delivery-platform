<?php

namespace App\Models;

use Database\Factories\ServiceFeeDistanceSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $base_meters
 * @property string $base_fee
 * @property int $step_meters
 * @property string $step_fee
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'base_meters',
    'base_fee',
    'step_meters',
    'step_fee',
])]
class ServiceFeeDistanceSetting extends Model
{
    /** @use HasFactory<ServiceFeeDistanceSettingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_meters' => 'integer',
            'base_fee' => 'decimal:2',
            'step_meters' => 'integer',
            'step_fee' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        $settings = self::query()->latest('id')->first();

        if ($settings !== null) {
            return $settings;
        }

        return self::query()->create([
            'base_meters' => 1000,
            'base_fee' => 50,
            'step_meters' => 500,
            'step_fee' => 5,
        ]);
    }
}
