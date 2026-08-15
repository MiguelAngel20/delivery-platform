<?php

use App\Enums\UpgradeRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_upgrade_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')
                ->constrained('businesses')
                ->restrictOnDelete();
            $table->foreignId('requested_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('requested_quantity')->default(1);
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('business_branches')
                ->restrictOnDelete();
            $table->string('status', 30)->default(UpgradeRequestStatus::Pending->value);
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_upgrade_requests');
    }
};
