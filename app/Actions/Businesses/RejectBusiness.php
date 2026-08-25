<?php

namespace App\Actions\Businesses;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectBusiness
{
    public function handle(Business $business, string $reason): Business
    {
        return DB::transaction(function () use ($business, $reason): Business {
            /** @var Business $locked */
            $locked = Business::query()
                ->whereKey($business->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== BusinessStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden rechazar empresas pendientes.',
                ]);
            }

            $updated = Business::query()
                ->whereKey($locked->id)
                ->where('status', BusinessStatus::PendingApproval)
                ->update([
                    'status' => BusinessStatus::Rejected,
                    'rejection_reason' => $reason,
                    'approved_by_user_id' => null,
                    'approved_at' => null,
                ]);

            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden rechazar empresas pendientes.',
                ]);
            }

            return $locked->fresh();
        });
    }
}
