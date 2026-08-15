<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $push_enabled
 * @property bool $order_updates
 * @property bool $new_orders
 * @property bool $driver_offers
 * @property bool $finance_updates
 * @property bool $incident_updates
 * @property bool $custom_order_updates
 * @property bool $system_updates
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'push_enabled',
    'order_updates',
    'new_orders',
    'driver_offers',
    'finance_updates',
    'incident_updates',
    'custom_order_updates',
    'system_updates',
])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'push_enabled' => true,
        'order_updates' => true,
        'new_orders' => true,
        'driver_offers' => true,
        'finance_updates' => false,
        'incident_updates' => true,
        'custom_order_updates' => true,
        'system_updates' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push_enabled' => 'boolean',
            'order_updates' => 'boolean',
            'new_orders' => 'boolean',
            'driver_offers' => 'boolean',
            'finance_updates' => 'boolean',
            'incident_updates' => 'boolean',
            'custom_order_updates' => 'boolean',
            'system_updates' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
