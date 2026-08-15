<?php

use App\Enums\CollectionParty;
use App\Enums\PaymentMethod;
use App\Enums\SettlementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->decimal('products_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('customer_total', 12, 2);
            $table->decimal('business_amount', 12, 2);
            $table->decimal('driver_earning', 12, 2);
            $table->decimal('platform_earning', 12, 2);
            $table->string('payment_method', 30)->default(PaymentMethod::Cash->value);
            $table->string('collection_party', 30)->default(CollectionParty::Driver->value);
            $table->string('settlement_status', 30)->default(SettlementStatus::Open->value);
            $table->timestamps();

            $table->index('settlement_status');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_financials');
    }
};
