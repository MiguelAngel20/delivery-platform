<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['driver_id', 'business_id']);
            $table->index('driver_id');
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_businesses');
    }
};
