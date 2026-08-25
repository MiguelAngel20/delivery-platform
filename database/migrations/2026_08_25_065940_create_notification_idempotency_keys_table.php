<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_idempotency_keys', function (Blueprint $table) {
            $table->id();
            // Logical key e.g. "notif:12:order:99:accepted" or "otp:mail:{uuid}" or "push:12:…"
            $table->string('idempotency_key', 191)->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_idempotency_keys');
    }
};
