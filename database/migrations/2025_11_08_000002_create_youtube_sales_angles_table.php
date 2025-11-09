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
        Schema::create('youtube_sales_angles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_video_id')->constrained('youtube_videos')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion');
            $table->text('copy_ejemplo');
            $table->string('enfoque')->nullable(); // dolor, sueño, objeción, etc.
            $table->string('tipo_contenido')->nullable(); // anuncio, landing, email, social
            $table->integer('orden')->default(0);
            $table->timestamps();
            
            $table->index('youtube_video_id');
            $table->index('orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_sales_angles');
    }
};
