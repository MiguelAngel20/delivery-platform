<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('opening_hours');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('email');
        });

        $branches = DB::table('business_branches')
            ->select(['business_id', 'opening_hours'])
            ->whereNotNull('opening_hours')
            ->orderBy('id')
            ->get()
            ->unique('business_id');

        foreach ($branches as $branch) {
            DB::table('businesses')
                ->where('id', $branch->business_id)
                ->update(['opening_hours' => $branch->opening_hours]);
        }
    }
};
