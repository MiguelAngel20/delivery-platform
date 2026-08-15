<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_user_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_user_id')
                ->constrained('business_users')
                ->restrictOnDelete();
            $table->foreignId('branch_id')
                ->constrained('business_branches')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['business_user_id', 'branch_id']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user_branches');
    }
};
