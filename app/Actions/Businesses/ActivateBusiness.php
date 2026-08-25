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
        return DB::transaction(function () use ($business): Business {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BusinessStatus::Suspended) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden reactivar empresas suspendidas.',
                ]);
            }

            $updated = Business::query()
                ->whereKey($locked->id)
                ->where('status', BusinessStatus::Suspended)
                ->update([
                    'status' => BusinessStatus::Active,
                    'suspension_reason' => null,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden reactivar empresas suspendidas.',
                ]);
            }

            return $locked->fresh();
        });
    }
}
