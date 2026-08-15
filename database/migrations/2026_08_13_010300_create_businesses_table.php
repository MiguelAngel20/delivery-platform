<?php

use App\Enums\BusinessDeliveryMode;
use App\Enums\BusinessOperationMode;
use App\Enums\BusinessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('business_type', 60)->nullable();
            $table->string('operation_mode', 40)->default(BusinessOperationMode::Partner->value);
            $table->string('delivery_mode', 40)->default(BusinessDeliveryMode::PlatformDrivers->value);
            $table->string('status', 30)->default(BusinessStatus::PendingApproval->value);
            $table->string('logo_path')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('operation_mode');
            $table->index('delivery_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
