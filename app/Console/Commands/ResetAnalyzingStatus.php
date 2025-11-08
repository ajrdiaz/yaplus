<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\YoutubeVideo;

class ResetAnalyzingStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:reset-analyzing {video_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resetear el estado is_analyzing de videos de YouTube';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $videoId = $this->argument('video_id');

        if ($videoId) {
            $video = YoutubeVideo::find($videoId);
            
            if (!$video) {
                $this->error("Video con ID {$videoId} no encontrado.");
                return 1;
            }

            $video->is_analyzing = false;
            $video->save();
            
            $this->info("Estado de análisis reseteado para el video: {$video->title}");
        } else {
            // Resetear todos los videos que estén marcados como analizando
            $count = YoutubeVideo::where('is_analyzing', true)->update(['is_analyzing' => false]);
            $this->info("Estado de análisis reseteado para {$count} video(s).");
        }

        return 0;
    }
}
