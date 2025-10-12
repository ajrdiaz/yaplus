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
        Schema::table('products', function (Blueprint $table) {
            // Campos de consolidación de buyer personas
            $table->json('top_5_buyer_personas')->nullable()->after('descripcion');
            $table->json('pain_points_consolidados')->nullable();
            $table->json('motivaciones_consolidadas')->nullable();
            $table->json('suenos_consolidados')->nullable();
            $table->json('objeciones_consolidadas')->nullable();
            $table->json('keywords_consolidadas')->nullable();
            $table->json('canales_preferidos')->nullable();
            $table->json('demografia_promedio')->nullable();

            // Insights y resúmenes
            $table->text('insights_youtube')->nullable();
            $table->text('insights_google_forms')->nullable();

            // Métricas de consolidación
            $table->integer('total_buyer_personas')->default(0);
            $table->integer('total_youtube_personas')->default(0);
            $table->integer('total_google_form_personas')->default(0);
            $table->timestamp('ultima_consolidacion')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'top_5_buyer_personas',
                'pain_points_consolidados',
                'motivaciones_consolidadas',
                'suenos_consolidados',
                'objeciones_consolidadas',
                'keywords_consolidadas',
                'canales_preferidos',
                'demografia_promedio',
                'insights_youtube',
                'insights_google_forms',
                'total_buyer_personas',
                'total_youtube_personas',
                'total_google_form_personas',
                'ultima_consolidacion',
            ]);
        });
    }
};
