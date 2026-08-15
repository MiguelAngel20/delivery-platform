<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->decimal('merchant_latitude', 10, 7)->nullable()->after('merchant_phone');
            $table->decimal('merchant_longitude', 10, 7)->nullable()->after('merchant_latitude');
            $table->string('merchant_formatted_address')->nullable()->after('merchant_longitude');
            $table->string('merchant_place_id', 255)->nullable()->after('merchant_formatted_address');
            $table->text('merchant_reference')->nullable()->after('merchant_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('custom_order_requests', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_latitude',
                'merchant_longitude',
                'merchant_formatted_address',
                'merchant_place_id',
                'merchant_reference',
            ]);
        });
    }
};
