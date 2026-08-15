<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assigned_driver_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('drivers')
                ->restrictOnDelete();

            $table->timestamp('driver_arrived_at')->nullable()->after('ready_at');

            $table->index(['order_status', 'assigned_driver_id']);
            $table->index(['assigned_driver_id', 'order_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_driver_id');
            $table->dropColumn('driver_arrived_at');
        });
    }
};
