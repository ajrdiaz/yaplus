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
        Schema::table('youtube_comments', function (Blueprint $table) {
            // Agregar foreign key para relacionar con youtube_videos
            $table->foreignId('youtube_video_id')->nullable()->after('id')->constrained('youtube_videos')->onDelete('cascade');
            
            // Remover campos que ahora estarán en youtube_videos
            $table->dropColumn(['video_title', 'video_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('youtube_comments', function (Blueprint $table) {
            // Restaurar campos
            $table->string('video_title')->nullable();
            $table->string('video_url')->nullable();
            
            // Eliminar foreign key
            $table->dropForeign(['youtube_video_id']);
            $table->dropColumn('youtube_video_id');
        });
    }
};
