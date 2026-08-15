<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->boolean('is_external_item')->default(false);
            $table->timestamps();

            $table->index(['promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_items');
    }
};
