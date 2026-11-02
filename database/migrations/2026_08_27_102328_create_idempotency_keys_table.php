<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            // Episode 18. The cashier's tablet loses Wi-Fi mid-checkout and retries.
            // Without this table the customer is charged twice.
            $table->string('key')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Same key + different body = the client reused a key by mistake. That's
            // a 409, not a replay — replaying would return the wrong receipt.
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
