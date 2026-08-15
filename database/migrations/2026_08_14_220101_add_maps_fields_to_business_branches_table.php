<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('address_text');
            $table->string('place_id', 255)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('business_branches', function (Blueprint $table) {
            $table->dropColumn(['formatted_address', 'place_id']);
        });
    }
};
