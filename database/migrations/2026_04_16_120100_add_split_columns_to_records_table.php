<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->string('split_method')->default('equal')->after('included_mates');
            $table->unsignedSmallInteger('bill_period_days')->nullable()->after('split_method');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn(['bill_period_days', 'split_method']);
        });
    }
};

