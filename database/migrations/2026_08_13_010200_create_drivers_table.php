<?php

use App\Enums\DriverApprovalStatus;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverPaymentModel;
use App\Enums\DriverScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('approval_status', 30)->default(DriverApprovalStatus::Pending->value);
            $table->string('availability_status', 30)->default(DriverAvailabilityStatus::Offline->value);
            $table->string('driver_scope', 30)->default(DriverScope::Platform->value);
            $table->string('payment_model', 30)->default(DriverPaymentModel::PlatformRate->value);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('approval_status');
            $table->index('availability_status');
            $table->index('driver_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
