<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('ends_at');
            $table->date('recurrence_starts_on')->nullable()->after('is_recurring');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_starts_on');
            $table->json('recurring_hours')->nullable()->after('recurrence_ends_on');

            $table->index(['status', 'is_recurring']);
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_recurring']);
            $table->dropColumn([
                'is_recurring',
                'recurrence_starts_on',
                'recurrence_ends_on',
                'recurring_hours',
            ]);
        });
    }
};
