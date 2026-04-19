<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('houses', function (Blueprint $table) {
            $table->decimal('guest_day_weight_percent', 8, 2)
                ->default(100)
                ->comment('Each guest night counts as this % of one full bill day (100 = 1:1)');
        });
    }

    public function down(): void
    {
        Schema::table('houses', function (Blueprint $table) {
            $table->dropColumn('guest_day_weight_percent');
        });
    }
};
