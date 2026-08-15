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
        if ($business->status !== BusinessStatus::Active) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden suspender empresas activas.',
            ]);
        }

        return DB::transaction(function () use ($business, $reason): Business {
            $business->update([
                'status' => BusinessStatus::Suspended,
                'suspension_reason' => $reason,
            ]);

            return $business->fresh();
        });
    }
}
