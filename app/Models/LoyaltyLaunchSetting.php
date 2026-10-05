<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property bool $enabled
 * @property int|null $updated_by
 */
#[Fillable([
    'enabled',
    'updated_by',
])]
class LoyaltyLaunchSetting extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
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
        $settings = self::query()->first();

        if ($settings !== null) {
            return $settings;
        }

        return self::query()->create([
            'enabled' => true,
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
