<?php

namespace App\Actions\Drivers;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Notifications\Auth\DriverEmailVerification;
use Illuminate\Validation\ValidationException;

class ResendDriverEmailVerification
{
    public function handle(Driver $driver): void
    {
        $driver->loadMissing('user');
        $user = $driver->user;

        if ($user === null || $user->role !== UserRole::Driver || ! filled($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'Este repartidor no tiene un correo para verificar.',
            ]);
        }

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => 'Este correo ya está verificado.',
            ]);
        }

        $user->notify(new DriverEmailVerification);
    }
}
