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
        if ($business->status !== BusinessStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden rechazar empresas pendientes.',
            ]);
        }

        return DB::transaction(function () use ($business, $reason): Business {
            $business->update([
                'status' => BusinessStatus::Rejected,
                'rejection_reason' => $reason,
                'approved_by_user_id' => null,
                'approved_at' => null,
            ]);

            return $business->fresh();
        });
    }
}
