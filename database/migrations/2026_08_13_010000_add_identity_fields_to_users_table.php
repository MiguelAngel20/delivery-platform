<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->softDeletes();
        });

        $users = DB::table('users')->select(['id', 'name'])->orderBy('id')->get();

        foreach ($users as $user) {
            $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];
            $firstName = ($parts[0] ?? '') !== '' ? $parts[0] : 'Usuario';
            $lastName = $parts[1] ?? 'RIDE';

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => sprintf('+5025555%04d', $user->id),
            ]);
        }

        // Ensure columns are required even when the table is empty.
        DB::table('users')
            ->whereNull('first_name')
            ->update(['first_name' => 'Usuario']);
        DB::table('users')
            ->whereNull('last_name')
            ->update(['last_name' => 'RIDE']);
        DB::table('users')
            ->whereNull('phone')
            ->update(['phone' => DB::raw("CONCAT('+5025555', LPAD(id, 4, '0'))")]);

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });

        DB::statement('ALTER TABLE users MODIFY first_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY last_name VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY phone VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'phone_verified_at',
            ]);
        });
    }
};
