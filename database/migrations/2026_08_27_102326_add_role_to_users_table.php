<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Episode 10. Deliberately a plain column, not a package: the point is
            // to teach 401 vs 403, not to configure Spatie.
            $table->string('role')->default('cashier')->after('email'); // manager | cashier
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
