<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_settlements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('trip_id');

            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');

            $table->string('from_name')->nullable();
            $table->string('to_name')->nullable();

            $table->decimal('amount', 10, 2);

            $table->string('source', 16)->default('engine');
            $table->string('type', 32)->default('expense');
            $table->string('title')->nullable();
            $table->text('note')->nullable();

            $table->enum('status', ['pending', 'paid'])->default('pending');

            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['trip_id']);
            $table->index(['trip_id', 'status', 'source']);

            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_settlements');
    }
};
