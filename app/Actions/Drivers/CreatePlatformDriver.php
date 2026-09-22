<?php

namespace App\Actions\Drivers;

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Driver;
use App\Models\User;
use App\Notifications\Auth\DriverEmailVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePlatformDriver
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string,
     *     pays_commission?: bool,
     *     commission_per_order?: float|string
     * }  $data
     */
    public function handle(array $data, ?User $actor = null): Driver
    {
        $email = strtolower(trim($data['email']));
        $phone = trim($data['phone']);

        $existing = User::query()
            ->where(function ($query) use ($email, $phone): void {
                $query->where('email', $email)
                    ->orWhere('phone', $phone);
            })
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                $existing->email === $email ? 'email' : 'phone' => $existing->email === $email
                    ? 'Ya existe un usuario con este correo.'
                    : 'Ya existe un usuario con este teléfono.',
            ]);
        }

        $driver = DB::transaction(function () use ($data, $email, $phone, $actor): Driver {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $phone,
                'password' => (string) config('business.users.temporary_password', '12344321'),
                'must_change_password' => true,
                'role' => UserRole::Driver,
                'status' => UserStatus::Active,
                'email_verified_at' => null,
            ]);

            return Driver::query()->create([
                'user_id' => $user->id,
                'approval_status' => DriverApprovalStatus::Approved,
                'availability_status' => DriverAvailabilityStatus::Offline,
                'driver_scope' => DriverScope::Platform,
                'payment_model' => DriverPaymentModel::PlatformRate,
                'pays_commission' => (bool) ($data['pays_commission'] ?? false),
                'commission_per_order' => number_format(
                    (float) (($data['pays_commission'] ?? false) ? ($data['commission_per_order'] ?? 0) : 0),
                    2,
                    '.',
                    '',
                ),
                'approved_by_user_id' => $actor?->id,
                'approved_at' => now(),
            ]);
        });

        $driver->load('user');
        $driver->user?->notify(new DriverEmailVerification);

        return $driver;
    }
}
