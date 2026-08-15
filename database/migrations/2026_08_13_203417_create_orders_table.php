<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('business_branches')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default(OrderType::Business->value);
            $table->string('operation_mode', 40);
            $table->string('order_status', 40)->default(OrderStatus::PendingBusiness->value);
            $table->string('payment_status', 30)->default(PaymentStatus::Pending->value);
            $table->string('payment_method', 30)->default(PaymentMethod::Cash->value);
            $table->decimal('subtotal_before_discount', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('subtotal_after_discount', 12, 2);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('estimated_preparation_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('business_accepted_at')->nullable();
            $table->timestamp('preparation_started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'order_status']);
            $table->index(['branch_id', 'order_status']);
            $table->index(['order_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
