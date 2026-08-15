<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('payment_method', 30)->default(PaymentMethod::Cash->value);
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default(PaymentStatus::Pending->value);
            $table->string('received_by_type', 30)->nullable();
            $table->unsignedBigInteger('received_by_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['received_by_type', 'received_by_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
