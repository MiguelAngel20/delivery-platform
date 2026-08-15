<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_trip_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_trip_id')->constrained('delivery_trips')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedInteger('sequence')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->unique(['delivery_trip_id', 'order_id']);
            $table->index(['delivery_trip_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trip_orders');
    }
};
