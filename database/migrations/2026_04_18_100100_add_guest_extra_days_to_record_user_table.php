<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('record_user', function (Blueprint $table) {
            $table->unsignedSmallInteger('guest_extra_days')->default(0)->after('excluded_days');
        });
    }

    public function down(): void
    {
        Schema::table('record_user', function (Blueprint $table) {
            $table->dropColumn('guest_extra_days');
        });
    }
};
