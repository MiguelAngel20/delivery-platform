<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateBusiness
{
    public function handle(Business $business): Business
    {
        if ($business->status !== BusinessStatus::Suspended) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden reactivar empresas suspendidas.',
            ]);
        }

        return DB::transaction(function () use ($business): Business {
            $business->update([
                'status' => BusinessStatus::Active,
                'suspension_reason' => null,
            ]);

            return $business->fresh();
        });
    }
}
