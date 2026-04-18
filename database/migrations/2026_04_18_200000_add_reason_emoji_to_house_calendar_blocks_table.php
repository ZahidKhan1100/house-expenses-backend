<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('house_calendar_blocks', function (Blueprint $table) {
            $table->string('reason_emoji', 16)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('house_calendar_blocks', function (Blueprint $table) {
            $table->dropColumn('reason_emoji');
        });
    }
};
