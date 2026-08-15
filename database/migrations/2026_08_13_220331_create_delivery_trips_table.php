<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('business_branches')->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index(['driver_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trips');
    }
};
