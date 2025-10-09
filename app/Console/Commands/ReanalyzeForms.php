<?php

namespace App\Console\Commands;

use App\Models\FormSurvey;
use App\Models\FormResponseAnalysis;
use App\Services\FormAnalysisService;
use Illuminate\Console\Command;

class ReanalyzeForms extends Command
{
    protected $signature = 'forms:reanalyze {survey_id?}';
    protected $description = 'Re-analizar respuestas de formularios con IA';

    public function handle(FormAnalysisService $service)
    {
        $surveyId = $this->argument('survey_id');
        
        if ($surveyId) {
            $survey = FormSurvey::findOrFail($surveyId);
            $surveys = collect([$survey]);
        } else {
            $surveys = FormSurvey::all();
        }

        foreach ($surveys as $survey) {
            $this->info("📊 Encuesta: {$survey->form_name}");
            $this->info("   Spreadsheet ID: {$survey->spreadsheet_id}");
            
            // Contar antes de eliminar
            $deleted = FormResponseAnalysis::where('form_survey_id', $survey->id)->count();
            $this->info("   🗑️  Análisis anteriores: {$deleted}");
            
            // Eliminar análisis anteriores
            if ($deleted > 0) {
                FormResponseAnalysis::where('form_survey_id', $survey->id)->delete();
                $this->info("   ✓ Eliminados {$deleted} análisis anteriores");
            }
            
            // Contar respuestas a analizar
            $totalToAnalyze = $survey->responses()->whereRaw('LENGTH(combined_text) > 20')->count();
            $this->info("   📝 Respuestas a analizar: {$totalToAnalyze}");
            $this->info("   ⏱️  Tiempo estimado: ~" . ceil($totalToAnalyze * 0.5 / 60) . " minutos");
            $this->newLine();
            
            // Crear barra de progreso
            $bar = $this->output->createProgressBar($totalToAnalyze);
            $bar->start();
            
            // Re-analizar
            $result = $service->analyzeSurveyResponses($survey->id, null, function() use ($bar) {
                $bar->advance();
            });
            
            $bar->finish();
            $this->newLine(2);
            
            // Mostrar resultados
            $this->info("✅ Análisis completado:");
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Total', $result['total']],
                    ['Analizadas', $result['analyzed']],
                    ['Omitidas', $result['skipped']],
                    ['Errores', $result['errors']],
                ]
            );
            
            // Verificar categorías
            $this->newLine();
            $this->info("📊 Categorías guardadas:");
            $categories = FormResponseAnalysis::where('form_survey_id', $survey->id)
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->orderByDesc('count')
                ->get();
            
            foreach ($categories as $cat) {
                $hasPipes = strpos($cat->category, '|') !== false;
                $status = $hasPipes ? '⚠️' : '✓';
                $this->line("   {$status} {$cat->category}: {$cat->count} respuestas");
            }
            
            $withPipes = FormResponseAnalysis::where('form_survey_id', $survey->id)
                ->where('category', 'LIKE', '%|%')
                ->count();
            
            if ($withPipes > 0) {
                $this->newLine();
                $this->warn("⚠️  Hay {$withPipes} respuestas con categorías incorrectas (contienen pipes)");
            } else {
                $this->newLine();
                $this->info("✅ Todas las categorías están correctas");
            }
            
            $this->newLine();
        }

        return 0;
    }
}
