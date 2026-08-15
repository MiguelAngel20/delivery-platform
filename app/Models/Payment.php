<?php

namespace App\Models;

use App\Enums\FinancialPartyType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property PaymentMethod $payment_method
 * @property string $amount
 * @property PaymentStatus $status
 * @property FinancialPartyType|null $received_by_type
 * @property int|null $received_by_id
 * @property Carbon|null $paid_at
 */
#[Fillable([
    'order_id',
    'payment_method',
    'amount',
    'status',
    'received_by_type',
    'received_by_id',
    'paid_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'received_by_type' => FinancialPartyType::class,
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
