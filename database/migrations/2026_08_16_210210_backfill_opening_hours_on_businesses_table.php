<?php

use App\Support\BusinessHours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = json_encode(BusinessHours::defaults());

        DB::table('businesses')
            ->whereNull('opening_hours')
            ->update(['opening_hours' => $defaults]);
    }

    public function down(): void
    {
        //
    }
};
