<?php

use App\Enums\CustomerTrustLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('trust_level', 30)->default(CustomerTrustLevel::New->value);
            $table->timestamps();

            $table->index('trust_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
