<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuspendBusiness
{
    public function handle(Business $business, string $reason): Business
    {
        return DB::transaction(function () use ($business, $reason): Business {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BusinessStatus::Active) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden suspender empresas activas.',
                ]);
            }

            $updated = Business::query()
                ->whereKey($locked->id)
                ->where('status', BusinessStatus::Active)
                ->update([
                    'status' => BusinessStatus::Suspended,
                    'suspension_reason' => $reason,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden suspender empresas activas.',
                ]);
            }

            return $locked->fresh();
        });
    }
}
