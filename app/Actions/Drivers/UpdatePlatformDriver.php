<?php

namespace App\Actions\Drivers;

use App\Models\Driver;
use App\Models\User;
use App\Notifications\Auth\DriverEmailVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePlatformDriver
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone: string
     * }  $data
     */
    public function handle(Driver $driver, array $data): Driver
    {
        $driver->loadMissing('user');

        $user = $driver->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'El repartidor no tiene un usuario asociado.',
            ]);
        }

        $email = strtolower(trim($data['email']));
        $phone = trim($data['phone']);

        $emailTaken = User::query()
            ->where('email', $email)
            ->whereKeyNot($user->id)
            ->exists();

        if ($emailTaken) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe un usuario con este correo.',
            ]);
        }

        $phoneTaken = User::query()
            ->where('phone', $phone)
            ->whereKeyNot($user->id)
            ->exists();

        if ($phoneTaken) {
            throw ValidationException::withMessages([
                'phone' => 'Ya existe un usuario con este teléfono.',
            ]);
        }

        $emailChanged = strtolower((string) $user->email) !== $email;

        DB::transaction(function () use ($user, $data, $email, $phone, $emailChanged): void {
            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $email,
                'phone' => $phone,
            ]);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        if ($emailChanged) {
            $user->notify(new DriverEmailVerification);
        }

        if (array_key_exists('pays_commission', $data) || array_key_exists('commission_per_order', $data)) {
            $pays = (bool) ($data['pays_commission'] ?? $driver->pays_commission);
            $driver->forceFill([
                'pays_commission' => $pays,
                'commission_per_order' => number_format(
                    (float) ($pays ? ($data['commission_per_order'] ?? $driver->commission_per_order) : 0),
                    2,
                    '.',
                    '',
                ),
            ])->save();
        }

        return $driver->refresh()->load('user');
    }
}
