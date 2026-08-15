<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_current_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique('driver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_current_locations');
    }
};
