<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $locked->load('user');
            $user = $locked->user;

            if ($user !== null && $user->role !== UserRole::Customer) {
                throw ValidationException::withMessages([
                    'customer' => 'Solo se pueden eliminar cuentas de cliente.',
                ]);
            }

            if ($user !== null && ($user->driver()->exists() || $user->businessMemberships()->exists())) {
                throw ValidationException::withMessages([
                    'customer' => 'Esta cuenta no se puede eliminar porque también está ligada a otro perfil.',
                ]);
            }

            if ($locked->hasOrderHistory()) {
                $this->retire($user);

                return;
            }

            $this->purge($locked, $user);
        });
    }

    private function retire(?User $user): void
    {
        if ($user === null || $user->trashed()) {
            return;
        }

        $user->pushDevices()->delete();
        $this->forgetSessions($user);

        $user->forceFill([
            'email' => null,
            'phone' => 'retired-'.$user->id,
            'status' => UserStatus::Inactive,
            'phone_verified_at' => null,
            'email_verified_at' => null,
        ])->save();

        $user->delete();
    }

    private function purge(Customer $customer, ?User $user): void
    {
        $customer->addresses()->forceDelete();
        $customer->metrics()->delete();

        if ($user !== null) {
            $user->pushDevices()->delete();
            $user->notificationPreference()?->delete();
            $user->notifications()->delete();
            $this->forgetSessions($user);
        }

        $customer->delete();
        $user?->forceDelete();
    }

    private function forgetSessions(User $user): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
