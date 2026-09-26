<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->string('option_cluster_name', 100)->nullable()->after('option_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_options', function (Blueprint $table) {
            $table->dropColumn('option_cluster_name');
        });
    }
};
