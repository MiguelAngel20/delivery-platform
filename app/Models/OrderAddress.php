<?php

namespace App\Models;

use App\Enums\OrderAddressSource;
use App\Enums\OrderAddressType;
use Database\Factories\OrderAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property OrderAddressType $type
 * @property OrderAddressSource $source
 * @property string $address_text
 * @property string|null $formatted_address
 * @property string|null $reference
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $place_id
 * @property string|null $google_maps_url
 * @property Carbon|null $created_at
 */
#[Fillable([
    'order_id',
    'type',
    'source',
    'address_text',
    'formatted_address',
    'reference',
    'latitude',
    'longitude',
    'place_id',
    'google_maps_url',
    'created_at',
])]
class OrderAddress extends Model
{
    /** @use HasFactory<OrderAddressFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderAddressType::class,
            'source' => OrderAddressSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
