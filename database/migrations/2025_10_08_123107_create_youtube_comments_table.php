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
        Schema::create('youtube_comments', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->string('video_title')->nullable();
            $table->string('video_url');
            $table->string('comment_id')->unique();
            $table->string('author');
            $table->string('author_image')->nullable();
            $table->text('text');
            $table->text('text_original');
            $table->integer('like_count')->default(0);
            $table->integer('reply_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('comment_updated_at')->nullable();
            $table->json('replies')->nullable();
            $table->timestamps();
            
            $table->index('video_id');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_comments');
    }
};
