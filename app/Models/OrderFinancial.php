<?php

namespace App\Models;

use App\Enums\CollectionParty;
use App\Enums\PaymentMethod;
use App\Enums\SettlementStatus;
use Database\Factories\OrderFinancialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $products_amount
 * @property string $discount_amount
 * @property string $service_fee
 * @property string $delivery_fee
 * @property string $customer_total
 * @property string $business_amount
 * @property string $driver_earning
 * @property string $platform_earning
 * @property PaymentMethod $payment_method
 * @property CollectionParty $collection_party
 * @property SettlementStatus $settlement_status
 */
#[Fillable([
    'order_id',
    'products_amount',
    'discount_amount',
    'service_fee',
    'delivery_fee',
    'customer_total',
    'business_amount',
    'driver_earning',
    'platform_earning',
    'payment_method',
    'collection_party',
    'settlement_status',
])]
class OrderFinancial extends Model
{
    /** @use HasFactory<OrderFinancialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'products_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'customer_total' => 'decimal:2',
            'business_amount' => 'decimal:2',
            'driver_earning' => 'decimal:2',
            'platform_earning' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'collection_party' => CollectionParty::class,
            'settlement_status' => SettlementStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
