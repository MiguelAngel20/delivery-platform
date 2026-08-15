<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_logistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedInteger('pickup_to_delivery_distance_meters')->nullable();
            $table->unsignedInteger('estimated_delivery_duration_seconds')->nullable();
            $table->string('distance_method', 30)->nullable();
            $table->foreignId('coverage_zone_id')
                ->nullable()
                ->constrained('coverage_zones')
                ->nullOnDelete();
            $table->string('coverage_zone_name')->nullable();
            $table->string('coverage_zone_type', 30)->nullable();
            $table->unsignedInteger('coverage_radius_meters')->nullable();
            $table->timestamps();

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_logistics');
    }
};
