<?php

use App\Enums\BusinessUserRole;
use App\Models\BusinessBranch;
use App\Models\BusinessUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        BusinessUser::query()
            ->where('role', BusinessUserRole::BusinessAdmin)
            ->whereDoesntHave('branches')
            ->each(function (BusinessUser $membership): void {
                $branchId = BusinessBranch::query()
                    ->where('business_id', $membership->business_id)
                    ->orderBy('id')
                    ->value('id');

                if ($branchId === null) {
                    return;
                }

                DB::table('business_user_branches')->insertOrIgnore([
                    'business_user_id' => $membership->id,
                    'branch_id' => $branchId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // No-op: branch assignments may have been edited after backfill.
    }
};
