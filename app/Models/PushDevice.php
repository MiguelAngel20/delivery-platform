<?php

namespace App\Models;

use App\Enums\PushDeviceType;
use Database\Factories\PushDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $token
 * @property PushDeviceType $device_type
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $device_name
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'provider',
    'token',
    'device_type',
    'browser',
    'platform',
    'device_name',
    'is_active',
    'last_used_at',
])]
class PushDevice extends Model
{
    /** @use HasFactory<PushDeviceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'fcm',
        'device_type' => 'web',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => PushDeviceType::class,
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
