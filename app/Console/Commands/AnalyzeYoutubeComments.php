<?php

namespace App\Console\Commands;

use App\Models\YoutubeVideo;
use App\Services\CommentAnalysisService;
use Illuminate\Console\Command;

class AnalyzeYoutubeComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:analyze 
                            {video_id? : ID del video a analizar}
                            {--all : Analizar todos los videos}
                            {--limit= : Límite de comentarios por video}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analizar comentarios de YouTube con IA para identificar buyer personas';

    /**
     * Execute the console command.
     */
    public function handle(CommentAnalysisService $analysisService)
    {
        $this->info('🤖 Iniciando análisis con IA...');

        if ($this->option('all')) {
            $this->analyzeAllVideos($analysisService);
        } elseif ($this->argument('video_id')) {
            $this->analyzeVideo($this->argument('video_id'), $analysisService);
        } else {
            $this->error('Debes especificar un video_id o usar --all');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function analyzeVideo($videoId, CommentAnalysisService $analysisService)
    {
        $video = YoutubeVideo::find($videoId);

        if (!$video) {
            $this->error("Video con ID {$videoId} no encontrado");
            return;
        }

        $this->info("📹 Analizando: {$video->title}");
        
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;
        
        $results = $analysisService->analyzeVideoComments($videoId, $limit);

        $this->newLine();
        $this->info("✅ Análisis completado:");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total comentarios', $results['total']],
                ['Analizados', $results['analyzed']],
                ['Errores', $results['errors']],
            ]
        );

        // Mostrar estadísticas
        $stats = $analysisService->getVideoAnalysisStats($videoId);
        
        $this->newLine();
        $this->info("📊 Estadísticas del análisis:");
        $this->table(
            ['Categoría', 'Cantidad'],
            collect($stats['by_category'])->map(function ($count, $category) {
                return [$category, $count];
            })->toArray()
        );
    }

    private function analyzeAllVideos(CommentAnalysisService $analysisService)
    {
        $videos = YoutubeVideo::withCount('comments')->get();

        if ($videos->isEmpty()) {
            $this->warn('No hay videos para analizar');
            return;
        }

        $this->info("📹 Se analizarán {$videos->count()} videos");
        
        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        foreach ($videos as $video) {
            $limit = $this->option('limit') ? (int)$this->option('limit') : null;
            $analysisService->analyzeVideoComments($video->id, $limit);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Análisis completado para todos los videos');
    }
}
