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
        Schema::table('form_surveys', function (Blueprint $table) {
            $table->dropColumn([
                'product_name',
                'product_description',
                'target_audience',
                'research_goal',
                'additional_context',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_surveys', function (Blueprint $table) {
            $table->string('product_name')->nullable();
            $table->text('product_description')->nullable();
            $table->text('target_audience')->nullable();
            $table->text('research_goal')->nullable();
            $table->text('additional_context')->nullable();
        });
    }
};
