<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_activity_suspensions', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->string('reason')->default('maintenance');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('platform_activity_suspensions')->insert([
            'is_active' => false,
            'reason' => 'maintenance',
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activity_suspensions');
    }
};
