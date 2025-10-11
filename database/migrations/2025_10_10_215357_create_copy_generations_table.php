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
        Schema::create('copy_generations', function (Blueprint $table) {
            $table->id();
            $table->morphs('buyer_persona'); // buyer_persona_id y buyer_persona_type
            $table->string('copy_type'); // facebook_ad, google_ad, landing_hero, email, instagram_post
            $table->string('name')->nullable(); // Nombre descriptivo del copy
            $table->text('headline')->nullable(); // Titular principal
            $table->text('subheadline')->nullable(); // Subtítulo
            $table->text('body')->nullable(); // Cuerpo del texto
            $table->string('cta')->nullable(); // Call to action
            $table->json('additional_data')->nullable(); // Datos adicionales según el tipo
            $table->integer('character_count')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copy_generations');
    }
};
