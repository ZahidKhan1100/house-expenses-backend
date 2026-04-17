<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('platform', 16)->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index('user_id');
        });

        foreach (DB::table('users')->whereNotNull('expo_push_token')->where('expo_push_token', '!=', '')->cursor() as $row) {
            DB::table('user_push_tokens')->insertOrIgnore([
                'user_id' => $row->id,
                'token' => $row->expo_push_token,
                'platform' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_push_tokens');
    }
};
