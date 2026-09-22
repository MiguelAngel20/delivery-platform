<?php

namespace App\Actions\Drivers;

use App\Enums\UserStatus;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePlatformDriver
{
    public function handle(Driver $driver): void
    {
        $driver->loadMissing('user');

        if ($driver->user === null) {
            throw ValidationException::withMessages([
                'driver' => 'El repartidor no tiene un usuario asociado.',
            ]);
        }

        DB::transaction(function () use ($driver): void {
            $driver->branches()->detach();
            $driver->businesses()->detach();

            $driver->user?->forceFill([
                'status' => UserStatus::Inactive,
            ])->save();

            $driver->user?->delete();
            $driver->delete();
        });
    }
}
