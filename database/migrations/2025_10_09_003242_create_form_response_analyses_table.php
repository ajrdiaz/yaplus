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
        Schema::create('form_response_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_response_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_survey_id')->constrained()->onDelete('cascade');
            
            // Análisis de IA (igual que YouTube)
            $table->string('category'); // necesidad, dolor, sueño, objecion, pregunta, experiencia_positiva, experiencia_negativa, sugerencia, otro
            $table->string('sentiment'); // positivo, negativo, neutral
            $table->integer('relevance_score'); // 1-10
            $table->boolean('is_relevant')->default(false);
            $table->text('ia_analysis');
            $table->json('keywords')->nullable();
            $table->json('insights')->nullable(); // buyer_insight, pain_point, opportunity
            
            $table->timestamps();
            
            $table->index('form_response_id');
            $table->index('form_survey_id');
            $table->index('category');
            $table->index('sentiment');
            $table->index('is_relevant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_response_analyses');
    }
};
