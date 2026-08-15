<?php

use App\Enums\CustomerTrustLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->restrictOnDelete();
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->unsignedInteger('late_cancellations')->default(0);
            $table->unsignedInteger('rejected_at_delivery')->default(0);
            $table->unsignedInteger('payment_incidents')->default(0);
            $table->unsignedInteger('incident_count')->default(0);
            $table->unsignedInteger('responsible_incidents')->default(0);
            $table->decimal('trust_score', 5, 2)->default(0);
            $table->string('trust_level', 30)->default(CustomerTrustLevel::New->value);
            $table->timestamp('last_recalculated_at')->nullable();
            $table->timestamps();

            $table->index('trust_level');
            $table->index('trust_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_metrics');
    }
};
