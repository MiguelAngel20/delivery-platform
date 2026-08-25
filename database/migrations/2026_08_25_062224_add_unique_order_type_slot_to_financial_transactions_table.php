<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invariant: non-adjustment transaction types are unique per order.
     * Adjustment may repeat (FinancialTransactionType::isUniquePerOrder()).
     *
     * MySQL 8: UNIQUE allows multiple NULLs, so a generated column that is
     * NULL for adjustments acts as a partial unique index.
     */
    public function up(): void
    {
        $duplicates = DB::table('financial_transactions')
            ->select('order_id', 'transaction_type', DB::raw('COUNT(*) as aggregate'))
            ->where('transaction_type', '!=', 'adjustment')
            ->groupBy('order_id', 'transaction_type')
            ->having('aggregate', '>', 1)
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot add unique_order_type_key: duplicate non-adjustment transactions exist.',
            );
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->string('unique_order_type_key', 80)
                ->nullable()
                ->storedAs("case when `transaction_type` = 'adjustment' then null else concat(`order_id`, ':', `transaction_type`) end");
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->unique('unique_order_type_key', 'financial_transactions_unique_order_type_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropUnique('financial_transactions_unique_order_type_key_unique');
            $table->dropColumn('unique_order_type_key');
        });
    }
};
