<?php

namespace App\Actions\Customers;

use App\Contracts\FirebaseIdTokenVerifier;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\FirebaseIdTokenException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ConfirmCustomerPhone
{
    public function __construct(private readonly FirebaseIdTokenVerifier $tokens) {}

    public function handle(User $user, string $idToken): void
    {
        abort_unless($user->role === UserRole::Customer, 403);

        try {
            $verified = $this->tokens->verify($idToken);
        } catch (FirebaseIdTokenException $exception) {
            throw ValidationException::withMessages([
                'firebase_id_token' => $this->message($exception->reason),
            ]);
        }

        if (! PhoneNumber::same($verified->phone, (string) $user->phone)) {
            Log::warning('Customer phone verification mismatch', [
                'user_id' => $user->id,
                'account_phone' => PhoneNumber::mask((string) $user->phone),
                'token_phone' => PhoneNumber::mask($verified->phone),
            ]);

            throw ValidationException::withMessages([
                'firebase_id_token' => 'El teléfono verificado no coincide con el de tu cuenta.',
            ]);
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();
    }

    private function message(string $reason): string
    {
        return match ($reason) {
            'expired' => 'La verificación expiró. Solicita un código nuevo.',
            'unavailable' => 'No se pudo verificar el teléfono en este momento. Inténtalo de nuevo.',
            default => 'No se pudo confirmar la verificación del teléfono.',
        };
    }
}
