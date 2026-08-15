<?php

namespace App\Models;

use App\Enums\CustomerTrustLevel;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property CustomerTrustLevel $trust_level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'trust_level'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trust_level' => CustomerTrustLevel::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function customOrderRequests(): HasMany
    {
        return $this->hasMany(CustomOrderRequest::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(CustomerMetric::class);
    }

    public function isRestricted(): bool
    {
        return $this->trust_level->isRestricted();
    }

    public function isBlocked(): bool
    {
        return $this->trust_level->isBlocked();
    }
}
