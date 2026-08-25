<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invariant: at most one Accepted assignment per order at a time.
     * Rejected / Expired / Cancelled may coexist historically for the same order.
     *
     * MySQL 8: generated column NULL when not accepted + UNIQUE ≈ partial unique index.
     */
    public function up(): void
    {
        $duplicates = DB::table('driver_assignments')
            ->select('order_id', DB::raw('COUNT(*) as aggregate'))
            ->where('status', 'accepted')
            ->groupBy('order_id')
            ->having('aggregate', '>', 1)
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot add accepted_order_slot: multiple Accepted assignments exist for the same order.',
            );
        }

        Schema::table('driver_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('accepted_order_slot')
                ->nullable()
                ->storedAs("case when `status` = 'accepted' then `order_id` else null end");
        });

        Schema::table('driver_assignments', function (Blueprint $table) {
            $table->unique('accepted_order_slot', 'driver_assignments_accepted_order_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('driver_assignments', function (Blueprint $table) {
            $table->dropUnique('driver_assignments_accepted_order_slot_unique');
            $table->dropColumn('accepted_order_slot');
        });
    }
};
