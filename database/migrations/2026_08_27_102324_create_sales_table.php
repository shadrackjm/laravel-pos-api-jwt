<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Public identifier. We never expose the sequential `id` in the API —
            // guessable IDs invite the IDOR problem we tackle in episode 15.
            $table->uuid('uuid')->unique();
            $table->string('receipt_number')->unique();
            $table->foreignId('user_id')->constrained();          // the cashier who rang it up
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total', 10, 2);
            $table->string('status')->default('completed');       // completed | refunded
            $table->timestamps();

            $table->index('created_at');                          // reports filter by date
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
