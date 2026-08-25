<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * At most one active default address per customer (MySQL 8 generated UNIQUE).
     */
    public function up(): void
    {
        $duplicates = DB::table('customer_addresses')
            ->select('customer_id', DB::raw('COUNT(*) as aggregate'))
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->groupBy('customer_id')
            ->having('aggregate', '>', 1)
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot add default_customer_slot: multiple active defaults exist for the same customer.',
            );
        }

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('default_customer_slot')
                ->nullable()
                ->storedAs(
                    'case when `is_default` = 1 and `is_active` = 1 and `deleted_at` is null then `customer_id` else null end'
                );
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->unique('default_customer_slot', 'customer_addresses_default_customer_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropUnique('customer_addresses_default_customer_slot_unique');
            $table->dropColumn('default_customer_slot');
        });
    }
};
