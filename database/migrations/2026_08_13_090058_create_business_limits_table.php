<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')
                ->unique()
                ->constrained('businesses')
                ->restrictOnDelete();
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_business_admins')->default(1);
            $table->unsignedInteger('max_employees_per_branch')->default(3);
            $table->timestamps();
        });

        $defaults = config('business.defaults');

        $businessIds = DB::table('businesses')->pluck('id');

        $now = now();

        foreach ($businessIds as $businessId) {
            DB::table('business_limits')->insert([
                'business_id' => $businessId,
                'max_branches' => $defaults['max_branches'],
                'max_business_admins' => $defaults['max_business_admins'],
                'max_employees_per_branch' => $defaults['max_employees_per_branch'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_limits');
    }
};
