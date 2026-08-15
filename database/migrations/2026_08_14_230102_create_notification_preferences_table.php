<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('order_updates')->default(true);
            $table->boolean('new_orders')->default(true);
            $table->boolean('driver_offers')->default(true);
            $table->boolean('finance_updates')->default(false);
            $table->boolean('incident_updates')->default(true);
            $table->boolean('custom_order_updates')->default(true);
            $table->boolean('system_updates')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
