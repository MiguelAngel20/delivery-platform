<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('service_fee_discount', 10, 2)->default(0)->after('service_fee');
            $table->string('loyalty_reward_type', 32)->nullable()->after('service_fee_discount');
            $table->index('loyalty_reward_type');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['loyalty_reward_type']);
            $table->dropColumn(['service_fee_discount', 'loyalty_reward_type']);
        });
    }
};
