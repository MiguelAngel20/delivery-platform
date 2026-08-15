<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('scope_type', 30);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('zone_type', 30)->default('radius');
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id', 'is_active']);
            $table->index(['is_active', 'zone_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_zones');
    }
};
