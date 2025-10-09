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
        Schema::create('form_surveys', function (Blueprint $table) {
            $table->id();
            $table->string('form_id')->unique()->comment('Google Form ID');
            $table->string('sheet_id')->nullable()->comment('Google Sheet ID vinculado');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('form_url')->nullable();
            $table->integer('responses_count')->default(0);
            
            // Contexto de negocio (igual que YouTube)
            $table->string('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('research_goal')->nullable();
            $table->text('additional_context')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_surveys');
    }
};
