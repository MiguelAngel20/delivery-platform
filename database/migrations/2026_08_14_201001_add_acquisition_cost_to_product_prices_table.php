<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('list_price');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropColumn('acquisition_cost');
        });
    }
};
