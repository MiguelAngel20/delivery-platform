<?php

namespace App\Models;

use App\Enums\PlatformSuspensionReason;
use Database\Factories\PlatformActivitySuspensionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $is_active
 * @property PlatformSuspensionReason $reason
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'is_active',
    'reason',
    'updated_by',
])]
class PlatformActivitySuspension extends Model
{
    /** @use HasFactory<PlatformActivitySuspensionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reason' => PlatformSuspensionReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        $settings = self::query()->latest('id')->first();

        if ($settings !== null) {
            return $settings;
        }

        return self::query()->create([
            'is_active' => false,
            'reason' => PlatformSuspensionReason::Maintenance,
            'updated_by' => null,
        ]);
    }
}
