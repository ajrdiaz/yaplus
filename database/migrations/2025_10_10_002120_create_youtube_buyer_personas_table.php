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
        Schema::create('youtube_buyer_personas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_video_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('edad')->nullable();
            $table->string('ocupacion')->nullable();
            $table->text('descripcion')->nullable();
            $table->json('motivaciones')->nullable();
            $table->json('pain_points')->nullable();
            $table->json('suenos')->nullable();
            $table->json('objeciones')->nullable();
            $table->text('comportamiento')->nullable();
            $table->json('canales_preferidos')->nullable();
            $table->json('keywords_clave')->nullable();
            $table->integer('porcentaje_audiencia')->default(0);
            $table->enum('nivel_prioridad', ['alta', 'media', 'baja'])->default('media');
            $table->text('estrategia_recomendada')->nullable();
            $table->integer('total_comments_analyzed')->default(0); // Metadatos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_buyer_personas');
    }
};
