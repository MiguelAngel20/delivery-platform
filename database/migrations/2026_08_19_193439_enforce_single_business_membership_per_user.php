<?php

use App\Enums\BusinessUserRole;
use App\Models\BusinessUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateUserIds = DB::table('business_users')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicateUserIds as $userId) {
            $memberships = BusinessUser::query()
                ->where('user_id', $userId)
                ->orderByRaw(
                    'CASE WHEN role = ? THEN 0 ELSE 1 END',
                    [BusinessUserRole::BusinessAdmin->value],
                )
                ->orderByDesc('updated_at')
                ->get();

            /** @var BusinessUser $keep */
            $keep = $memberships->first();

            $memberships->slice(1)->each(function (BusinessUser $membership): void {
                $membership->branches()->detach();
                $membership->delete();
            });

            $branchIds = $keep->branches()->pluck('business_branches.id');

            if ($branchIds->count() > 1) {
                $keep->branches()->sync([$branchIds->first()]);
            }
        }

        $membershipsWithManyBranches = DB::table('business_user_branches')
            ->select('business_user_id')
            ->groupBy('business_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('business_user_id');

        foreach ($membershipsWithManyBranches as $membershipId) {
            $branchId = DB::table('business_user_branches')
                ->where('business_user_id', $membershipId)
                ->orderBy('branch_id')
                ->value('branch_id');

            if ($branchId === null) {
                continue;
            }

            DB::table('business_user_branches')
                ->where('business_user_id', $membershipId)
                ->where('branch_id', '!=', $branchId)
                ->delete();
        }

        Schema::table('business_users', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_users', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
