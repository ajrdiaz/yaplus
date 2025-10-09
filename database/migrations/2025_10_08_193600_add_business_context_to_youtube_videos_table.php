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
            $table->string('product_name')->nullable()->after('url');
            $table->text('product_description')->nullable()->after('product_name');
            $table->string('target_audience')->nullable()->after('product_description');
            $table->text('research_goal')->nullable()->after('target_audience');
            $table->text('additional_context')->nullable()->after('research_goal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('youtube_videos', function (Blueprint $table) {
            $table->dropColumn([
                'product_name',
                'product_description',
                'target_audience',
                'research_goal',
                'additional_context'
            ]);
        });
    }
};
