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
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_survey_id')->constrained()->onDelete('cascade');
            $table->string('response_id')->unique()->comment('ID único de Google Forms');
            $table->string('respondent_email')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->json('raw_data')->comment('Respuestas completas en JSON');
            $table->text('combined_text')->nullable()->comment('Texto combinado de todas las respuestas para análisis');
            $table->timestamps();
            
            $table->index('form_survey_id');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
