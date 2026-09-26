<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->foreignId('option_cluster_id')
                ->nullable()
                ->after('option_group_id')
                ->constrained('product_option_clusters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('option_cluster_id');
        });
    }
};
