<?php

namespace App\Models;

use App\Enums\CustomOrderRequestStatus;
use Database\Factories\CustomOrderRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $business_id
 * @property int|null $branch_id
 * @property string|null $establishment_name
 * @property string $description
 * @property string|null $customer_notes
 * @property int|null $delivery_address_id
 * @property array<string, mixed>|null $temporary_delivery_address
 * @property string|null $merchant_address
 * @property string|null $merchant_phone
 * @property string|null $merchant_latitude
 * @property string|null $merchant_longitude
 * @property string|null $merchant_formatted_address
 * @property string|null $merchant_place_id
 * @property string|null $merchant_reference
 * @property CustomOrderRequestStatus $status
 * @property int|null $assigned_admin_user_id
 * @property int|null $quoted_order_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'customer_id',
    'business_id',
    'branch_id',
    'establishment_name',
    'description',
    'customer_notes',
    'delivery_address_id',
    'temporary_delivery_address',
    'merchant_address',
    'merchant_phone',
    'merchant_latitude',
    'merchant_longitude',
    'merchant_formatted_address',
    'merchant_place_id',
    'merchant_reference',
    'status',
    'assigned_admin_user_id',
    'quoted_order_id',
])]
class CustomOrderRequest extends Model
{
    /** @use HasFactory<CustomOrderRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_review',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temporary_delivery_address' => 'array',
            'status' => CustomOrderRequestStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_user_id');
    }

    public function quotedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'quoted_order_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(OrderQuote::class);
    }

    public function latestQuote(): ?OrderQuote
    {
        return $this->quotes()->latest('id')->first();
    }
}
