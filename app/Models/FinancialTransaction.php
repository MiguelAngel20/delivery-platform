<?php

namespace App\Models;

use App\Enums\FinancialPartyType;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Enums\PaymentMethod;
use Database\Factories\FinancialTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property FinancialPartyType|null $from_party_type
 * @property int|null $from_party_id
 * @property FinancialPartyType|null $to_party_type
 * @property int|null $to_party_id
 * @property FinancialTransactionType $transaction_type
 * @property string $amount
 * @property PaymentMethod $payment_method
 * @property FinancialTransactionStatus $status
 * @property string|null $description
 * @property string|null $idempotency_key
 * @property int|null $recorded_by_user_id
 * @property Carbon|null $settled_at
 */
#[Fillable([
    'order_id',
    'from_party_type',
    'from_party_id',
    'to_party_type',
    'to_party_id',
    'transaction_type',
    'amount',
    'payment_method',
    'status',
    'description',
    'idempotency_key',
    'recorded_by_user_id',
    'settled_at',
])]
class FinancialTransaction extends Model
{
    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_party_type' => FinancialPartyType::class,
            'to_party_type' => FinancialPartyType::class,
            'transaction_type' => FinancialTransactionType::class,
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'status' => FinancialTransactionStatus::class,
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
