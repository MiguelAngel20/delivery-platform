<?php

namespace App\Models;

use App\Enums\BusinessOperationMode;
use App\Enums\OrderAddressType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int $customer_id
 * @property int|null $branch_id
 * @property int|null $assigned_driver_id
 * @property int|null $created_by_user_id
 * @property OrderType $type
 * @property BusinessOperationMode $operation_mode
 * @property OrderStatus $order_status
 * @property PaymentStatus $payment_status
 * @property PaymentMethod $payment_method
 * @property string $subtotal_before_discount
 * @property string $discount_total
 * @property string $subtotal_after_discount
 * @property string $service_fee
 * @property string $delivery_fee
 * @property string $total
 * @property int|null $estimated_preparation_minutes
 * @property string|null $notes
 * @property string|null $merchant_name_snapshot
 * @property string|null $merchant_address_snapshot
 * @property string|null $merchant_phone_snapshot
 * @property Carbon|null $business_accepted_at
 * @property Carbon|null $preparation_started_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $driver_arrived_at
 * @property Carbon|null $picked_up_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_number',
    'customer_id',
    'branch_id',
    'assigned_driver_id',
    'created_by_user_id',
    'type',
    'operation_mode',
    'order_status',
    'payment_status',
    'payment_method',
    'subtotal_before_discount',
    'discount_total',
    'subtotal_after_discount',
    'service_fee',
    'delivery_fee',
    'total',
    'estimated_preparation_minutes',
    'notes',
    'merchant_name_snapshot',
    'merchant_address_snapshot',
    'merchant_phone_snapshot',
    'business_accepted_at',
    'preparation_started_at',
    'ready_at',
    'driver_arrived_at',
    'picked_up_at',
    'delivered_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'operation_mode' => BusinessOperationMode::class,
            'order_status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal_before_discount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'subtotal_after_discount' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'estimated_preparation_minutes' => 'integer',
            'business_accepted_at' => 'datetime',
            'preparation_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'driver_arrived_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessBranch::class, 'branch_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function tripOrder(): HasOne
    {
        return $this->hasOne(DeliveryTripOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)
            ->where('type', OrderAddressType::Delivery->value);
    }

    public function pickupAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)
            ->where('type', OrderAddressType::Pickup->value);
    }

    public function logistics(): HasOne
    {
        return $this->hasOne(OrderLogistics::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function financial(): HasOne
    {
        return $this->hasOne(OrderFinancial::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class)->orderBy('created_at');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(OrderCancellation::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->latest();
    }

    public function driverRating(): HasOne
    {
        return $this->hasOne(DriverRating::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(OrderQuote::class);
    }

    public function customOrderRequest(): HasOne
    {
        return $this->hasOne(CustomOrderRequest::class, 'quoted_order_id');
    }

    public function isCustom(): bool
    {
        return $this->type === OrderType::Custom;
    }

    public function isPlatformManaged(): bool
    {
        return $this->type === OrderType::Custom
            || $this->operation_mode === BusinessOperationMode::PlatformOperated;
    }

    public function merchantDisplayName(): ?string
    {
        return $this->merchant_name_snapshot
            ?: $this->branch?->business?->name;
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function scopeOrderByBusinessListPriority(Builder $query): void
    {
        $cases = collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status): string => sprintf(
                "when '%s' then %d",
                $status->value,
                $status->businessListSortPriority(),
            ))
            ->implode(' ');

        $query->orderByRaw("case order_status {$cases} else 2 end");
    }
}
