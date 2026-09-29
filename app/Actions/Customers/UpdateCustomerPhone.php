<?php

namespace App\Actions\Customers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PhoneNumber;

class UpdateCustomerPhone
{
    public function handle(User $user, string $phone): void
    {
        abort_unless($user->role === UserRole::Customer, 403);

        $canonical = PhoneNumber::canonicalize($phone);

        if (PhoneNumber::same($canonical, (string) $user->phone)) {
            return;
        }

        $user->forceFill([
            'phone' => $canonical,
        ])->save();
    }
}
