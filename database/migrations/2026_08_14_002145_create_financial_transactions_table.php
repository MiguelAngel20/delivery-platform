<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('from_party_type', 30)->nullable();
            $table->unsignedBigInteger('from_party_id')->nullable();
            $table->string('to_party_type', 30)->nullable();
            $table->unsignedBigInteger('to_party_id')->nullable();
            $table->string('transaction_type', 50);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30);
            $table->string('status', 30);
            $table->text('description')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'transaction_type']);
            $table->index(['transaction_type', 'status']);
            $table->index(['from_party_type', 'from_party_id']);
            $table->index(['to_party_type', 'to_party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
