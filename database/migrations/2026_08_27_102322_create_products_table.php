<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sku')->unique();
            // Money is decimal — never a float column. See episode 16.
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->timestamps();

            // Episode 22: searching and filtering on unindexed columns is the
            // second-biggest API performance killer after N+1.
            $table->index('name');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
