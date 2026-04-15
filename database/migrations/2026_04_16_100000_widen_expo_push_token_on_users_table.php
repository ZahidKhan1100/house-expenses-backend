<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY expo_push_token VARCHAR(512) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN expo_push_token TYPE VARCHAR(512)');
        }
        // sqlite: 255 default is usually enough; skip or use text if needed
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY expo_push_token VARCHAR(255) NULL');
        }
    }
};
