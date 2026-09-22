<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->boolean('pays_commission')->default(false)->after('payment_model');
            $table->decimal('commission_per_order', 8, 2)->default(0)->after('pays_commission');
        });

        Schema::table('order_financials', function (Blueprint $table) {
            $table->decimal('driver_commission', 12, 2)->default(0)->after('driver_earning');
            $table->timestamp('commission_settled_at')->nullable()->after('driver_commission');
            $table->foreignId('commission_settled_by')->nullable()->after('commission_settled_at')
                ->constrained('users')->nullOnDelete();

            $table->index('commission_settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_financials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_settled_by');
            $table->dropColumn(['driver_commission', 'commission_settled_at']);
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['pays_commission', 'commission_per_order']);
        });
    }
};
