<?php

namespace App\Models;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property UserRole $role
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $phone_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'first_name',
    'last_name',
    'name',
    'email',
    'phone',
    'password',
    'role',
    'status',
    'email_verified_at',
    'phone_verified_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = trim(implode(' ', array_filter([
                    $user->first_name,
                    $user->last_name,
                ])));

                return;
            }

            if ($user->isDirty('name')) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
                $user->first_name = ($parts[0] ?? '') !== '' ? $parts[0] : $user->first_name;
                $user->last_name = $parts[1] ?? ($user->last_name ?: 'RIDE');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function businessMemberships(): HasMany
    {
        return $this->hasMany(BusinessUser::class);
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasRole(UserRole::SystemAdmin);
    }

    public function canAccessBusiness(): bool
    {
        return $this->hasRole(...UserRole::businessRoles());
    }

    public function canAccessDriver(): bool
    {
        return $this->hasRole(UserRole::Driver);
    }

    public function canAccessCustomer(): bool
    {
        return $this->hasRole(UserRole::Customer);
    }

    public function isActive(): bool
    {
        return $this->status->canAuthenticate();
    }

    public function homeRoute(): string
    {
        return route($this->role->homeRouteName());
    }

    public function loginRoute(): string
    {
        return route($this->role->loginRouteName());
    }

    public function activeBusinessMembership(?Business $business = null): ?BusinessUser
    {
        $query = $this->businessMemberships()
            ->where('status', BusinessUserStatus::Active);

        if ($business !== null) {
            $query->where('business_id', $business->id);
        }

        return $query->first();
    }

    public function canAccessBranch(BusinessBranch $branch): bool
    {
        if ($this->hasRole(UserRole::SystemAdmin)) {
            return true;
        }

        $membership = $this->businessMemberships()
            ->where('business_id', $branch->business_id)
            ->where('status', BusinessUserStatus::Active)
            ->first();

        if ($membership === null) {
            return false;
        }

        if ($membership->role === BusinessUserRole::BusinessAdmin) {
            return true;
        }

        return $membership->branches()
            ->where('business_branches.id', $branch->id)
            ->exists();
    }

    public function managesBusiness(Business $business): bool
    {
        return $this->hasRole(UserRole::SystemAdmin)
            || $this->businessMemberships()
                ->where('business_id', $business->id)
                ->where('role', BusinessUserRole::BusinessAdmin)
                ->where('status', BusinessUserStatus::Active)
                ->exists();
    }
}
