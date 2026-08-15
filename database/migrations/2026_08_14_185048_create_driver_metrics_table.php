<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained('drivers')->restrictOnDelete();
            $table->unsignedInteger('offered_orders')->default(0);
            $table->unsignedInteger('accepted_orders')->default(0);
            $table->unsignedInteger('rejected_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('responsible_cancellations')->default(0);
            $table->unsignedInteger('incident_count')->default(0);
            $table->unsignedInteger('responsible_incidents')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('total_ratings')->default(0);
            $table->decimal('trust_score', 5, 2)->default(0);
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();

            $table->index('trust_score');
            $table->index('average_rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_metrics');
    }
};
