<?php

use App\Enums\CancellationReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancelled_by_type', 30);
            $table->string('reason_code', 60);
            $table->text('reason')->nullable();
            $table->string('previous_order_status', 40);
            $table->string('responsibility', 30);
            $table->string('review_status', 30)->default(CancellationReviewStatus::NotRequired->value);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('cancelled_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('review_status');
            $table->index('responsibility');
            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cancellations');
    }
};
