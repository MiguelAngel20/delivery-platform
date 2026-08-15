<?php

use App\Enums\CustomOrderRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('business_branches')->restrictOnDelete();
            $table->string('establishment_name', 150)->nullable();
            $table->text('description');
            $table->text('customer_notes')->nullable();
            $table->foreignId('delivery_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->json('temporary_delivery_address')->nullable();
            $table->string('merchant_address', 500)->nullable();
            $table->string('merchant_phone', 30)->nullable();
            $table->string('status', 40)->default(CustomOrderRequestStatus::PendingReview->value);
            $table->foreignId('assigned_admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quoted_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('assigned_admin_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_order_requests');
    }
};
