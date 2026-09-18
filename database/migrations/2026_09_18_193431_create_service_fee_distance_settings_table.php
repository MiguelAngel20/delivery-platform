<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fee_distance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('base_meters')->default(1000);
            $table->decimal('base_fee', 12, 2)->default(50);
            $table->unsignedInteger('step_meters')->default(500);
            $table->decimal('step_fee', 12, 2)->default(5);
            $table->timestamps();
        });

        DB::table('service_fee_distance_settings')->insert([
            'base_meters' => 1000,
            'base_fee' => 50,
            'step_meters' => 500,
            'step_fee' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee_distance_settings');
    }
};
