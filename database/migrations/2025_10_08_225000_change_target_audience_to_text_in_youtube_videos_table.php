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
        Schema::table('youtube_videos', function (Blueprint $table) {
            // Cambiar target_audience de VARCHAR(255) a TEXT
            $table->text('target_audience')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('youtube_videos', function (Blueprint $table) {
            // Revertir a VARCHAR(255)
            $table->string('target_audience', 255)->nullable()->change();
        });
    }
};
