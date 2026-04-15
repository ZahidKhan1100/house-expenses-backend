<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('karma_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('house_id')->nullable()->index();
            $table->integer('points');
            $table->string('reason', 64)->default('');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['house_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karma_ledger');
    }
};
