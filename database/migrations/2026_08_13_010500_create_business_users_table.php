<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 40)->default(BusinessUserRole::BusinessEmployee->value);
            $table->string('status', 30)->default(BusinessUserStatus::Active->value);
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_users');
    }
};
