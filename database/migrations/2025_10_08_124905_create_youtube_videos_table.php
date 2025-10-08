<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->string('video_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('channel_title');
            $table->string('thumbnail_url')->nullable();
            $table->string('thumbnail_default')->nullable();
            $table->string('thumbnail_medium')->nullable();
            $table->string('thumbnail_high')->nullable();
            $table->string('url');
            $table->string('duration')->nullable();
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('like_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            
            $table->index('video_id');
            $table->index('channel_id');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};
