<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveBusiness
{
    public function handle(Business $business, User $admin): Business
    {
        return DB::transaction(function () use ($business, $admin): Business {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BusinessStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden aprobar empresas pendientes.',
                ]);
            }

            $updated = Business::query()
                ->whereKey($locked->id)
                ->where('status', BusinessStatus::PendingApproval)
                ->update([
                    'status' => BusinessStatus::Active,
                    'approved_by_user_id' => $admin->id,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                    'suspension_reason' => null,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden aprobar empresas pendientes.',
                ]);
            }

            return $locked->fresh();
        });
    }
}
