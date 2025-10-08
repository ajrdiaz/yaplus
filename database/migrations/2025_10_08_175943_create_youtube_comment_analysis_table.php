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
        Schema::create('youtube_comment_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_comment_id')->constrained('youtube_comments')->onDelete('cascade');
            $table->foreignId('youtube_video_id')->constrained('youtube_videos')->onDelete('cascade');
            
            // Categorías de Buyer Persona
            $table->enum('category', [
                'necesidad',
                'dolor',
                'sueño',
                'objecion',
                'pregunta',
                'experiencia_positiva',
                'experiencia_negativa',
                'sugerencia',
                'otro'
            ])->nullable();
            
            // Análisis de la IA
            $table->text('ia_analysis')->nullable(); // Análisis completo de la IA
            $table->string('sentiment', 50)->nullable(); // positivo, negativo, neutral
            $table->integer('relevance_score')->nullable(); // 1-10
            $table->json('keywords')->nullable(); // Palabras clave extraídas
            $table->json('insights')->nullable(); // Insights específicos
            
            // Metadata
            $table->string('ai_model', 100)->default('gpt-4'); // Modelo usado
            $table->integer('tokens_used')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->boolean('is_relevant')->default(false); // Si es relevante para el buyer
            
            $table->timestamps();
            
            // Índices
            $table->index('category');
            $table->index('sentiment');
            $table->index('is_relevant');
            $table->index('relevance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_comment_analysis');
    }
};
