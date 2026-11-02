<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            // nullOnDelete, not cascade: deleting a product must never erase the
            // history of it having been sold. That's why product_name is frozen below.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots, frozen at the moment of sale. Prices and names change;
            // an old receipt must still show what was actually sold, for what.
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
