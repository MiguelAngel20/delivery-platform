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
        if ($business->status !== BusinessStatus::PendingApproval) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden aprobar empresas pendientes.',
            ]);
        }

        return DB::transaction(function () use ($business, $admin): Business {
            $business->update([
                'status' => BusinessStatus::Active,
                'approved_by_user_id' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'suspension_reason' => null,
            ]);

            return $business->fresh();
        });
    }
}
