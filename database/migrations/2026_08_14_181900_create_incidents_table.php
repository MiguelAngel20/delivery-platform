<?php

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 60);
            $table->string('severity', 30)->default(IncidentSeverity::Medium->value);
            $table->string('status', 30)->default(IncidentStatus::Open->value);
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index('severity');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
