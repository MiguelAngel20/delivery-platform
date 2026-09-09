<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('qualifying_orders_count')->default(0);
            $table->decimal('pending_reward_amount', 10, 2)->nullable();
            $table->string('pending_reward_calculator', 64)->nullable();
            $table->foreignId('reserved_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('reserved_reward_type', 32)->nullable();
            $table->decimal('reserved_reward_amount', 10, 2)->nullable();
            $table->timestamp('launch_consumed_at')->nullable();
            $table->foreignId('launch_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_accounts');
    }
};
