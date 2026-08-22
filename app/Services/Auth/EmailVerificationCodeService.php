<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    public function issue(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(
            $this->key($user),
            Hash::make($code),
            now()->addMinutes((int) config('business.customers.email_verification_ttl_minutes', 15)),
        );

        $user->notify(new CustomerEmailVerificationCode($code));
    }

    public function verify(User $user, string $code): bool
    {
        $hashed = Cache::get($this->key($user));

        if (! is_string($hashed) || ! Hash::check($code, $hashed)) {
            return false;
        }

        Cache::forget($this->key($user));

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return true;
    }

    private function key(User $user): string
    {
        return 'customer-email-otp:'.$user->id;
    }
}
