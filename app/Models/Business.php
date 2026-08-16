<?php

namespace App\Models;

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use Database\Factories\BusinessFactory;
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
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $business_type
 * @property BusinessOperationMode $operation_mode
 * @property BusinessDeliveryMode $delivery_mode
 * @property BusinessStatus $status
 * @property string|null $logo_path
 * @property string|null $banner_path
 * @property string|null $phone
 * @property string|null $email
 * @property array<int, array{day: string, is_open: bool, opens_at: string|null, closes_at: string|null}>|null $opening_hours
 * @property int|null $created_by_user_id
 * @property int|null $approved_by_user_id
 * @property Carbon|null $approved_at
 * @property string|null $rejection_reason
 * @property string|null $suspension_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name',
    'slug',
    'description',
    'business_type',
    'operation_mode',
    'delivery_mode',
    'status',
    'logo_path',
    'banner_path',
    'phone',
    'email',
    'opening_hours',
    'created_by_user_id',
    'approved_by_user_id',
    'approved_at',
    'rejection_reason',
    'suspension_reason',
])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operation_mode' => BusinessOperationMode::class,
            'delivery_mode' => BusinessDeliveryMode::class,
            'status' => BusinessStatus::class,
            'opening_hours' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(BusinessBranch::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function limits(): HasOne
    {
        return $this->hasOne(BusinessLimit::class);
    }

    public function upgradeRequests(): HasMany
    {
        return $this->hasMany(BusinessUpgradeRequest::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_users')
            ->withPivot(['role', 'status'])
            ->withTimestamps()
            ->using(BusinessUser::class);
    }

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(Driver::class, 'driver_businesses')
            ->withTimestamps()
            ->using(DriverBusiness::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
