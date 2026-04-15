<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('house_running_low_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('house_id')->index();
            $table->string('item_key', 32);
            $table->string('status', 16)->default('open'); // open | fulfilled
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('fulfilled_by')->nullable();
            $table->unsignedBigInteger('fulfilled_post_id')->nullable();
            $table->timestamps();

            $table->index(['house_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_running_low_requests');
    }
};
