<?php

namespace App\Console\Commands;

use App\Models\YoutubeComment;
use App\Models\YoutubeVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportYoutubeComments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:import 
                            {url : URL del video de YouTube}
                            {--max= : Máximo de comentarios a importar (vacío = todos)}
                            {--force : Forzar reimportación de comentarios existentes}
                            {--no-confirm : No pedir confirmación para videos grandes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importar comentarios de un video de YouTube';

    private string $apiKey;
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->apiKey = config('services.youtube.api_key');

        if (!$this->apiKey) {
            $this->error('❌ API Key de YouTube no configurada. Agrega YOUTUBE_API_KEY en tu archivo .env');
            return Command::FAILURE;
        }

        $url = $this->argument('url');
        $maxResults = $this->option('max');
        $force = $this->option('force');
        $noConfirm = $this->option('no-confirm');

        $this->info("🔍 Procesando URL: {$url}");

        // Extraer video ID
        $videoId = $this->extractVideoId($url);

        if (!$videoId) {
            $this->error('❌ URL de video inválida');
            return Command::FAILURE;
        }

        $this->info("📹 Video ID: {$videoId}");

        // Obtener información del video
        $this->info('📥 Obteniendo información del video...');
        $videoData = $this->getVideoInfo($videoId);

        if (!$videoData) {
            $this->error('❌ No se pudo obtener información del video');
            return Command::FAILURE;
        }

        $this->info("✅ Video: {$videoData['title']}");
        $this->info("👤 Canal: {$videoData['channel']}");
        
        $totalComments = $videoData['comment_count'];
        $this->info("💬 Comentarios totales en el video: " . number_format($totalComments));

        // Advertencia para videos con muchos comentarios
        if (!$noConfirm && $totalComments > 5000) {
            $this->warn("⚠️  ADVERTENCIA: Este video tiene más de 5,000 comentarios ({$totalComments})");
            $this->warn("⏱️  La importación puede tomar varios minutos.");
            $this->warn("💰 Consumirá aproximadamente " . ceil($totalComments / 100) . " unidades de tu cuota de API.");
            
            if (!$this->confirm('¿Deseas continuar?', false)) {
                $this->info('❌ Importación cancelada.');
                return Command::SUCCESS;
            }
        }

        // Crear o actualizar el video en la base de datos
        $youtubeVideo = YoutubeVideo::updateOrCreate(
            ['video_id' => $videoId],
            $videoData
        );

        // Determinar cuántos comentarios importar
        $limit = $maxResults ? (int)$maxResults : null;
        $importAll = $limit === null;
        
        if ($importAll) {
            $this->info("📥 Importando TODOS los comentarios del video...");
        } else {
            $this->info("📥 Importando hasta {$limit} comentarios...");
        }

        // Obtener comentarios con paginación
        $allComments = $this->getAllComments($videoId, $limit);

        if (empty($allComments)) {
            $this->error("\n❌ No se pudieron obtener los comentarios");
            return Command::FAILURE;
        }

        $totalToImport = count($allComments);
        $this->info("📊 Total de comentarios obtenidos: {$totalToImport}");
        
        $bar = $this->output->createProgressBar($totalToImport);
        $bar->start();

        $imported = 0;
        $skipped = 0;

        foreach ($allComments as $comment) {
            $exists = YoutubeComment::where('comment_id', $comment['comment_id'])->exists();

            if ($exists && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($exists && $force) {
                YoutubeComment::where('comment_id', $comment['comment_id'])->delete();
            }

            YoutubeComment::create([
                'youtube_video_id' => $youtubeVideo->id,
                'video_id' => $videoId,
                'comment_id' => $comment['comment_id'],
                'author' => $comment['author'],
                'author_image' => $comment['author_image'],
                'text' => $comment['text'],
                'text_original' => $comment['text_original'],
                'like_count' => $comment['like_count'],
                'reply_count' => $comment['reply_count'],
                'published_at' => $comment['published_at'],
                'comment_updated_at' => $comment['updated_at'],
                'replies' => $comment['replies'],
            ]);

            $imported++;
            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);
        $this->info("✅ Importación completada:");
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Importados', $imported],
                ['Omitidos (duplicados)', $skipped],
                ['Total', $imported + $skipped],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Extraer video ID de la URL
     */
    private function extractVideoId(string $url): ?string
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Obtener información del video
     */
    private function getVideoInfo(string $videoId): ?array
    {
        $response = Http::get("{$this->baseUrl}/videos", [
            'part' => 'snippet,statistics,contentDetails',
            'id' => $videoId,
            'key' => $this->apiKey,
        ]);

        if (!$response->successful() || empty($response->json()['items'])) {
            return null;
        }

        $video = $response->json()['items'][0];
        $snippet = $video['snippet'];
        $stats = $video['statistics'];
        $contentDetails = $video['contentDetails'] ?? [];

        return [
            'title' => $snippet['title'],
            'channel' => $snippet['channelTitle'],
            'description' => $snippet['description'] ?? null,
            'channel_id' => $snippet['channelId'],
            'channel_title' => $snippet['channelTitle'],
            'thumbnail_url' => $snippet['thumbnails']['default']['url'] ?? null,
            'thumbnail_default' => $snippet['thumbnails']['default']['url'] ?? null,
            'thumbnail_medium' => $snippet['thumbnails']['medium']['url'] ?? null,
            'thumbnail_high' => $snippet['thumbnails']['high']['url'] ?? null,
            'url' => "https://www.youtube.com/watch?v={$videoId}",
            'duration' => $contentDetails['duration'] ?? null,
            'view_count' => $stats['viewCount'] ?? 0,
            'like_count' => $stats['likeCount'] ?? 0,
            'comment_count' => $stats['commentCount'] ?? 0,
            'published_at' => $snippet['publishedAt'],
        ];
    }

    /**
     * Obtener TODOS los comentarios del video con paginación
     */
    private function getAllComments(string $videoId, ?int $limit = null): array
    {
        $allComments = [];
        $pageToken = null;
        $totalFetched = 0;

        do {
            $maxResults = 100; // YouTube permite máximo 100 por petición
            
            // Si hay límite, ajustar maxResults para la última petición
            if ($limit !== null && ($totalFetched + $maxResults) > $limit) {
                $maxResults = $limit - $totalFetched;
            }

            $params = [
                'part' => 'snippet,replies',
                'videoId' => $videoId,
                'key' => $this->apiKey,
                'maxResults' => $maxResults,
                'order' => 'time',
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::get("{$this->baseUrl}/commentThreads", $params);

            if (!$response->successful()) {
                $this->warn("\n⚠️  Error al obtener página de comentarios. Continuando con los obtenidos...");
                break;
            }

            $data = $response->json();

            foreach ($data['items'] ?? [] as $item) {
                $snippet = $item['snippet']['topLevelComment']['snippet'];

                $allComments[] = [
                    'comment_id' => $item['id'],
                    'author' => $snippet['authorDisplayName'],
                    'author_image' => $snippet['authorProfileImageUrl'],
                    'text' => $snippet['textDisplay'],
                    'text_original' => $snippet['textOriginal'],
                    'like_count' => $snippet['likeCount'],
                    'reply_count' => $item['snippet']['totalReplyCount'] ?? 0,
                    'published_at' => $snippet['publishedAt'],
                    'updated_at' => $snippet['updatedAt'],
                    'replies' => $this->formatReplies($item['replies'] ?? null),
                ];

                $totalFetched++;

                // Si alcanzamos el límite, detener
                if ($limit !== null && $totalFetched >= $limit) {
                    break 2;
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;

            // Pequeña pausa para no saturar la API
            if ($pageToken) {
                usleep(100000); // 0.1 segundos
            }

        } while ($pageToken);

        return $allComments;
    }

    /**
     * Formatear respuestas
     */
    private function formatReplies($replies): array
    {
        if (!$replies || !isset($replies['comments'])) {
            return [];
        }

        return collect($replies['comments'])->map(function ($reply) {
            $snippet = $reply['snippet'];

            return [
                'id' => $reply['id'],
                'author' => $snippet['authorDisplayName'],
                'author_image' => $snippet['authorProfileImageUrl'],
                'text' => $snippet['textDisplay'],
                'like_count' => $snippet['likeCount'],
                'published_at' => $snippet['publishedAt'],
            ];
        })->toArray();
    }
}
