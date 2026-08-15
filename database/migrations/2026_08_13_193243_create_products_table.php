<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('business_branches')->restrictOnDelete();
            $table->foreignId('product_category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('image_path', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_special_instructions')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index(['branch_id', 'is_available']);
            $table->index(['product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
