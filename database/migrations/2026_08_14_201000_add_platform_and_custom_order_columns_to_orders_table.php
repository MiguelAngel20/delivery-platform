<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->string('merchant_name_snapshot', 150)->nullable()->after('notes');
            $table->string('merchant_address_snapshot', 500)->nullable()->after('merchant_name_snapshot');
            $table->string('merchant_phone_snapshot', 30)->nullable()->after('merchant_address_snapshot');
            $table->index(['type', 'order_status']);
            $table->index(['operation_mode', 'order_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('business_branches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['type', 'order_status']);
            $table->dropIndex(['operation_mode', 'order_status']);
            $table->dropColumn([
                'merchant_name_snapshot',
                'merchant_address_snapshot',
                'merchant_phone_snapshot',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')
                ->references('id')
                ->on('business_branches')
                ->restrictOnDelete();
        });
    }
};
