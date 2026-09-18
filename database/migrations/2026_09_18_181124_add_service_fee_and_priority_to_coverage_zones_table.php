<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->decimal('service_fee', 12, 2)->nullable()->after('radius_meters');
            $table->unsignedInteger('priority')->default(0)->after('service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('coverage_zones', function (Blueprint $table) {
            $table->dropColumn(['service_fee', 'priority']);
        });
    }
};
