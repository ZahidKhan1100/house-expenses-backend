<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('house_id')->index();
            $table->unsignedBigInteger('expense_id')->nullable()->index();
            $table->unsignedBigInteger('record_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->index();
            $table->string('action', 32)->index();
            $table->string('summary', 512)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['house_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_audit_logs');
    }
};
