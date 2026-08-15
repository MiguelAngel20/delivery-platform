<?php

namespace App\Models;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $user_id
 * @property BusinessUserRole $role
 * @property BusinessUserStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'business_id',
    'user_id',
    'role',
    'status',
])]
class BusinessUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'business_users';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => BusinessUserRole::class,
            'status' => BusinessUserStatus::class,
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            BusinessBranch::class,
            'business_user_branches',
            'business_user_id',
            'branch_id',
        )->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === BusinessUserRole::BusinessAdmin;
    }

    public function isActive(): bool
    {
        return $this->status === BusinessUserStatus::Active;
    }
}
