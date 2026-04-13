<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('house_wall_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('house_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index(); // null for system cards
            $table->string('type', 20); // snippet | poll | system

            // Snippet fields
            $table->string('caption', 100)->nullable();
            $table->text('image_url')->nullable();

            // Poll fields
            $table->string('poll_question', 180)->nullable();

            // System payload (welcome/goal reached/etc)
            $table->json('system_payload')->nullable();

            $table->timestamps();
        });

        Schema::create('house_wall_poll_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->string('text', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('house_wall_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->unsignedBigInteger('option_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('house_wall_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
        });

        Schema::create('house_wall_fridge_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('house_id')->primary();
            $table->string('body', 255)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('house_member_statuses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->unsignedBigInteger('house_id')->index();
            $table->string('status', 10)->default('home'); // home | out | away
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_member_statuses');
        Schema::dropIfExists('house_wall_fridge_notes');
        Schema::dropIfExists('house_wall_reactions');
        Schema::dropIfExists('house_wall_poll_votes');
        Schema::dropIfExists('house_wall_poll_options');
        Schema::dropIfExists('house_wall_posts');
    }
};

