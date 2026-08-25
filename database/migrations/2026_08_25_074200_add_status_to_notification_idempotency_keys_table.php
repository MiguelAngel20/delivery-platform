<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claim lifecycle: claimed → sent | failed.
     * failed rows stay for audit and may be reclaimed for a technical retry.
     */
    public function up(): void
    {
        Schema::table('notification_idempotency_keys', function (Blueprint $table) {
            $table->string('status', 20)->default('sent')->after('idempotency_key');
            $table->unsignedInteger('attempts')->default(1)->after('status');
            $table->text('last_error')->nullable()->after('attempts');
            $table->timestamp('updated_at')->nullable()->after('created_at');
            $table->timestamp('completed_at')->nullable()->after('updated_at');
        });

        // Existing keys already blocked a successful delivery.
        DB::table('notification_idempotency_keys')->update([
            'status' => 'sent',
            'completed_at' => DB::raw('created_at'),
            'updated_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('notification_idempotency_keys', function (Blueprint $table) {
            $table->dropColumn(['status', 'attempts', 'last_error', 'updated_at', 'completed_at']);
        });
    }
};
