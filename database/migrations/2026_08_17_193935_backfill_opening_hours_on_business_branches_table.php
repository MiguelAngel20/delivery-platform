<?php

use App\Support\BusinessHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = json_encode(BusinessHours::defaults());

        foreach (DB::table('businesses')->select(['id', 'opening_hours'])->orderBy('id')->get() as $business) {
            $hours = $business->opening_hours ?: $defaults;

            if (is_array($hours)) {
                $hours = json_encode($hours);
            }

            DB::table('business_branches')
                ->where('business_id', $business->id)
                ->whereNull('opening_hours')
                ->update(['opening_hours' => $hours]);
        }

        DB::table('business_branches')
            ->whereNull('opening_hours')
            ->update(['opening_hours' => $defaults]);
    }

    public function down(): void
    {
        DB::table('business_branches')->update(['opening_hours' => null]);
    }
};
