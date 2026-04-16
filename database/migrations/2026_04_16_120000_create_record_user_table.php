<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('record_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('records')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('excluded_days')->default(0);
            $table->timestamps();

            $table->unique(['record_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_user');
    }
};

