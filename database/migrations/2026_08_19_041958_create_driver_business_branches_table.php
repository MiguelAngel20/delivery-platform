<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_business_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->constrained('business_branches')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['driver_id', 'branch_id']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_business_branches');
    }
};
