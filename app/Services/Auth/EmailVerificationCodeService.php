<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmailVerificationCodeService
{
    /**
     * Issue a new OTP for the user and queue the mail.
     *
     * Explicit calls (register / resend) always mint a fresh code and issuance id,
     * which invalidates any previous code stored under the same cache key.
     * Technical retries of the same queued notification share one issuance id and
     * must not send additional emails (see CustomerEmailVerificationCode).
     */
    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $issuanceId = (string) Str::uuid();

        Cache::put(
            $this->key($user),
            [
                'hash' => Hash::make($code),
                'issuance_id' => $issuanceId,
            ],
            now()->addMinutes((int) config('business.customers.email_verification_ttl_minutes', 15)),
        );

        $user->notify(new CustomerEmailVerificationCode($code, $issuanceId));

        return $issuanceId;
    }

    public function verify(User $user, string $code): bool
    {
        $payload = Cache::get($this->key($user));
        $hashed = $this->extractHash($payload);

        if ($hashed === null || ! Hash::check($code, $hashed)) {
            return false;
        }

        Cache::forget($this->key($user));

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return true;
    }

    private function extractHash(mixed $payload): ?string
    {
        if (is_string($payload)) {
            return $payload;
        }

        if (is_array($payload) && isset($payload['hash']) && is_string($payload['hash'])) {
            return $payload['hash'];
        }

        return null;
    }

    private function key(User $user): string
    {
        return 'customer-email-otp:'.$user->id;
    }
}
