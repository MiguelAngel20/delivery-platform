<?php

namespace App\Models;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use Database\Factories\DriverFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property DriverApprovalStatus $approval_status
 * @property DriverAvailabilityStatus $availability_status
 * @property DriverScope $driver_scope
 * @property DriverPaymentModel $payment_model
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'user_id',
    'approval_status',
    'availability_status',
    'driver_scope',
    'payment_model',
    'approved_by_user_id',
    'approved_at',
])]
class Driver extends Model
{
    /** @use HasFactory<DriverFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approval_status' => DriverApprovalStatus::class,
            'availability_status' => DriverAvailabilityStatus::class,
            'driver_scope' => DriverScope::class,
            'payment_model' => DriverPaymentModel::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'driver_businesses')
            ->withTimestamps()
            ->using(DriverBusiness::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessBranch::class,
            'driver_business_branches',
            'driver_id',
            'branch_id',
        )->withTimestamps();
    }

    public function isAssignedToBranch(int $branchId): bool
    {
        if ($this->relationLoaded('branches')) {
            return $this->branches->contains(
                fn (BusinessBranch $branch): bool => (int) $branch->id === $branchId,
            );
        }

        return $this->branches()->whereKey($branchId)->exists();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(DeliveryTrip::class);
    }

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'assigned_driver_id');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(DriverMetric::class);
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(DriverCurrentLocation::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(DriverRating::class);
    }
}
