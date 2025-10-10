<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\YoutubeComment;
use App\Models\YoutubeCommentAnalysis;
use App\Models\YoutubeVideo;
use App\Services\CommentAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class YoutubeController extends Controller
{
    /**
     * API Key de YouTube
     */
    private ?string $apiKey;

    /**
     * Base URL de la API de YouTube
     */
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.api_key');
    }

    /**
     * Obtener comentarios de un video de YouTube
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComments(Request $request)
    {
        $request->validate([
            'video_id' => 'required|string',
            'max_results' => 'nullable|integer|min:1|max:100',
            'page_token' => 'nullable|string',
        ]);

        $videoId = $request->input('video_id');
        $maxResults = $request->input('max_results', 20);
        $pageToken = $request->input('page_token');

        try {
            $response = Http::get("{$this->baseUrl}/commentThreads", [
                'part' => 'snippet,replies',
                'videoId' => $videoId,
                'key' => $this->apiKey,
                'maxResults' => $maxResults,
                'pageToken' => $pageToken,
                'order' => 'time', // time, relevance
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Formatear los comentarios para una respuesta más limpia
                $comments = collect($data['items'] ?? [])->map(function ($item) {
                    $snippet = $item['snippet']['topLevelComment']['snippet'];
                    
                    return [
                        'id' => $item['id'],
                        'author' => $snippet['authorDisplayName'],
                        'author_image' => $snippet['authorProfileImageUrl'],
                        'text' => $snippet['textDisplay'],
                        'text_original' => $snippet['textOriginal'],
                        'like_count' => $snippet['likeCount'],
                        'published_at' => $snippet['publishedAt'],
                        'updated_at' => $snippet['updatedAt'],
                        'reply_count' => $item['snippet']['totalReplyCount'] ?? 0,
                        'replies' => $this->formatReplies($item['replies'] ?? null),
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => [
                        'comments' => $comments,
                        'total_results' => $data['pageInfo']['totalResults'] ?? 0,
                        'next_page_token' => $data['nextPageToken'] ?? null,
                        'prev_page_token' => $data['prevPageToken'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comentarios de YouTube',
                'error' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::getComments', [
                'message' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener información de un video
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoInfo(Request $request)
    {
        $request->validate([
            'video_id' => 'required|string',
        ]);

        $videoId = $request->input('video_id');

        try {
            $response = Http::get("{$this->baseUrl}/videos", [
                'part' => 'snippet,statistics,contentDetails',
                'id' => $videoId,
                'key' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (empty($data['items'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Video no encontrado',
                    ], 404);
                }

                $video = $data['items'][0];

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $video['id'],
                        'title' => $video['snippet']['title'],
                        'description' => $video['snippet']['description'],
                        'channel_title' => $video['snippet']['channelTitle'],
                        'published_at' => $video['snippet']['publishedAt'],
                        'thumbnails' => $video['snippet']['thumbnails'],
                        'statistics' => [
                            'view_count' => $video['statistics']['viewCount'] ?? 0,
                            'like_count' => $video['statistics']['likeCount'] ?? 0,
                            'comment_count' => $video['statistics']['commentCount'] ?? 0,
                        ],
                        'duration' => $video['contentDetails']['duration'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del video',
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::getVideoInfo', [
                'message' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todos los comentarios con paginación
     *
     * @param string $videoId
     * @param int|null $limit
     * @return array
     */
    private function fetchAllComments(string $videoId, ?int $limit = null): array
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
     * Formatear respuestas de comentarios
     *
     * @param array|null $replies
     * @return array
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

    /**
     * Buscar videos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchVideos(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'max_results' => 'nullable|integer|min:1|max:50',
            'page_token' => 'nullable|string',
        ]);

        $query = $request->input('query');
        $maxResults = $request->input('max_results', 10);
        $pageToken = $request->input('page_token');

        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'part' => 'snippet',
                'q' => $query,
                'type' => 'video',
                'key' => $this->apiKey,
                'maxResults' => $maxResults,
                'pageToken' => $pageToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $videos = collect($data['items'] ?? [])->map(function ($item) {
                    return [
                        'video_id' => $item['id']['videoId'],
                        'title' => $item['snippet']['title'],
                        'description' => $item['snippet']['description'],
                        'channel_title' => $item['snippet']['channelTitle'],
                        'published_at' => $item['snippet']['publishedAt'],
                        'thumbnails' => $item['snippet']['thumbnails'],
                    ];
                });

                return response()->json([
                    'success' => true,
                    'data' => [
                        'videos' => $videos,
                        'total_results' => $data['pageInfo']['totalResults'] ?? 0,
                        'next_page_token' => $data['nextPageToken'] ?? null,
                        'prev_page_token' => $data['prevPageToken'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al buscar videos',
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::searchVideos', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar la página de gestión de comentarios
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $videos = YoutubeVideo::withCount(['comments', 'analyses'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Youtube/Index_Tabs', [
            'videos' => $videos,
        ]);
    }

    /**
     * Obtener comentarios de un video específico
     *
     * @param int $videoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoComments($videoId)
    {
        $video = YoutubeVideo::with(['comments' => function ($query) {
            $query->orderBy('published_at', 'desc');
        }])->findOrFail($videoId);

        return response()->json([
            'success' => true,
            'video' => $video,
            'comments' => $video->comments,
        ]);
    }

    /**
     * Extraer el video ID de una URL de YouTube
     *
     * @param string $url
     * @return string|null
     */
    private function extractVideoId(string $url): ?string
    {
        // Patrones para diferentes formatos de URL de YouTube
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

        // Si no coincide con ningún patrón, asumir que es el ID directo
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Importar comentarios desde YouTube y guardar en BD
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importComments(Request $request)
    {
        $request->validate([
            'video_url' => 'required|string',
            'max_results' => 'nullable|integer|min:1',
            'import_all' => 'nullable|boolean',
            // Contexto de negocio (opcional)
            'product_name' => 'nullable|string|max:255',
            'product_description' => 'nullable|string',
            'target_audience' => 'nullable|string',
            'research_goal' => 'nullable|string',
            'additional_context' => 'nullable|string',
        ]);

        $videoUrl = $request->input('video_url');
        $maxResults = $request->input('max_results', 50);
        $importAll = $request->input('import_all', false);

        // Extraer video ID de la URL
        $videoId = $this->extractVideoId($videoUrl);

        if (!$videoId) {
            return response()->json([
                'success' => false,
                'message' => 'URL de video inválida. Usa un formato como: https://www.youtube.com/watch?v=VIDEO_ID',
            ], 422);
        }

        try {
            // Obtener información del video
            $videoResponse = Http::get("{$this->baseUrl}/videos", [
                'part' => 'snippet,statistics',
                'id' => $videoId,
                'key' => $this->apiKey,
            ]);

            if (!$videoResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener información del video',
                ], $videoResponse->status());
            }

            $videoData = $videoResponse->json();
            
            if (empty($videoData['items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video no encontrado',
                ], 404);
            }

            $video = $videoData['items'][0];
            $videoSnippet = $video['snippet'];
            $videoStats = $video['statistics'];
            $videoContentDetails = $video['contentDetails'] ?? [];

            // Crear o actualizar el registro del video
            $youtubeVideo = YoutubeVideo::updateOrCreate(
                ['video_id' => $videoId],
                [
                    'title' => $videoSnippet['title'],
                    'description' => $videoSnippet['description'] ?? null,
                    'channel_id' => $videoSnippet['channelId'],
                    'channel_title' => $videoSnippet['channelTitle'],
                    'thumbnail_url' => $videoSnippet['thumbnails']['default']['url'] ?? null,
                    'thumbnail_default' => $videoSnippet['thumbnails']['default']['url'] ?? null,
                    'thumbnail_medium' => $videoSnippet['thumbnails']['medium']['url'] ?? null,
                    'thumbnail_high' => $videoSnippet['thumbnails']['high']['url'] ?? null,
                    'url' => $videoUrl,
                    'duration' => $videoContentDetails['duration'] ?? null,
                    'view_count' => $videoStats['viewCount'] ?? 0,
                    'like_count' => $videoStats['likeCount'] ?? 0,
                    'comment_count' => $videoStats['commentCount'] ?? 0,
                    'published_at' => $videoSnippet['publishedAt'],
                    // Contexto de negocio
                    'product_name' => $request->input('product_name'),
                    'product_description' => $request->input('product_description'),
                    'target_audience' => $request->input('target_audience'),
                    'research_goal' => $request->input('research_goal'),
                    'additional_context' => $request->input('additional_context'),
                ]
            );

            $totalComments = $videoStats['commentCount'] ?? 0;

            // Verificar si hay muchos comentarios y advertir
            if ($importAll && $totalComments > 5000) {
                return response()->json([
                    'success' => false,
                    'message' => "Este video tiene {$totalComments} comentarios. ¿Estás seguro de importar todos?",
                    'requires_confirmation' => true,
                    'total_comments' => $totalComments,
                    'estimated_time' => ceil($totalComments / 100) . ' minutos aproximadamente',
                ], 200);
            }

            // Obtener comentarios (con paginación si es necesario)
            $limit = $importAll ? null : $maxResults;
            $allComments = $this->fetchAllComments($videoId, $limit);

            if (empty($allComments)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron obtener comentarios',
                ], 500);
            }

            $importedCount = 0;
            $skippedCount = 0;

            foreach ($allComments as $commentData) {
                // Verificar si el comentario ya existe
                if (YoutubeComment::where('comment_id', $commentData['comment_id'])->exists()) {
                    $skippedCount++;
                    continue;
                }

                // Guardar comentario
                YoutubeComment::create([
                    'youtube_video_id' => $youtubeVideo->id,
                    'video_id' => $videoId,
                    'comment_id' => $commentData['comment_id'],
                    'author' => $commentData['author'],
                    'author_image' => $commentData['author_image'],
                    'text' => $commentData['text'],
                    'text_original' => $commentData['text_original'],
                    'like_count' => $commentData['like_count'],
                    'reply_count' => $commentData['reply_count'],
                    'published_at' => $commentData['published_at'],
                    'comment_updated_at' => $commentData['updated_at'],
                    'replies' => $commentData['replies'],
                ]);

                $importedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Se importaron {$importedCount} comentarios ({$skippedCount} duplicados omitidos)",
                'data' => [
                    'imported' => $importedCount,
                    'skipped' => $skippedCount,
                    'video_title' => $youtubeVideo->title,
                    'video_id' => $youtubeVideo->id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::importComments', [
                'message' => $e->getMessage(),
                'video_url' => $videoUrl,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un comentario
     *
     * @param YoutubeComment $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(YoutubeComment $comment)
    {
        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comentario eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::destroy', [
                'message' => $e->getMessage(),
                'comment_id' => $comment->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el comentario',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de comentarios
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        try {
            $stats = [
                'total_comments' => YoutubeComment::count(),
                'total_videos' => YoutubeComment::distinct('video_id')->count(),
                'total_likes' => YoutubeComment::sum('like_count'),
                'total_replies' => YoutubeComment::sum('reply_count'),
                'recent_comments' => YoutubeComment::orderBy('published_at', 'desc')->limit(5)->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en YoutubeController::stats', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
            ], 500);
        }
    }

    /**
     * Analizar comentarios de un video con IA
     *
     * @param Request $request
     * @param CommentAnalysisService $analysisService
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyzeComments(Request $request, CommentAnalysisService $analysisService)
    {
        $request->validate([
            'video_id' => 'required|exists:youtube_videos,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $videoId = $request->input('video_id');
        $limit = $request->input('limit', null);

        try {
            $results = $analysisService->analyzeVideoComments($videoId, $limit);

            return response()->json([
                'success' => true,
                'message' => "Análisis completado: {$results['analyzed']} comentarios analizados",
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al analizar comentarios', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al analizar comentarios con IA',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener análisis de comentarios de un video
     *
     * @param int $videoId
     * @param CommentAnalysisService $analysisService
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAnalysis($videoId, CommentAnalysisService $analysisService)
    {
        try {
            $video = YoutubeVideo::withCount('comments')->findOrFail($videoId);
            
            $analyses = YoutubeCommentAnalysis::with('comment')
                ->where('youtube_video_id', $videoId)
                ->orderBy('relevance_score', 'desc')
                ->get();

            $stats = $analysisService->getVideoAnalysisStats($videoId);

            // Cargar buyer personas existentes
            $existingPersonas = \App\Models\YoutubeBuyerPersona::where('youtube_video_id', $videoId)->get();

            return inertia('Youtube/Analysis', [
                'video' => $video,
                'analyses' => $analyses,
                'stats' => $stats,
                'existingPersonas' => $existingPersonas,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener análisis', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al obtener análisis');
        }
    }

    /**
     * Filtrar análisis por categoría o sentimiento
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterAnalysis(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:youtube_videos,id',
            'category' => 'nullable|string',
            'sentiment' => 'nullable|string',
            'min_relevance' => 'nullable|integer|min:1|max:10',
            'only_relevant' => 'nullable|boolean',
        ]);

        try {
            $query = YoutubeCommentAnalysis::with('comment')
                ->where('youtube_video_id', $request->video_id);

            if ($request->category) {
                $query->where('category', $request->category);
            }

            if ($request->sentiment) {
                $query->where('sentiment', $request->sentiment);
            }

            if ($request->min_relevance) {
                $query->where('relevance_score', '>=', $request->min_relevance);
            }

            if ($request->only_relevant) {
                $query->where('is_relevant', true);
            }

            $analyses = $query->orderBy('relevance_score', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $analyses,
                'count' => $analyses->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al filtrar análisis', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al filtrar análisis',
            ], 500);
        }
    }

    /**
     * Actualizar el contexto de negocio de un video
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\YoutubeVideo $video
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateContext(Request $request, YoutubeVideo $video)
    {
        try {
            $validated = $request->validate([
                'product_name' => 'nullable|string|max:255',
                'product_description' => 'nullable|string',
                'target_audience' => 'nullable|string',
                'research_goal' => 'nullable|string',
                'additional_context' => 'nullable|string',
            ]);

            $video->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Contexto de negocio actualizado correctamente',
                'video' => $video,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error al actualizar contexto de video', [
                'video_id' => $video->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el contexto de negocio',
            ], 500);
        }
    }

    /**
     * Eliminar un video con todos sus comentarios y análisis
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyVideo($id)
    {
        try {
            $video = YoutubeVideo::findOrFail($id);
            
            $videoTitle = $video->title;
            $commentsCount = $video->comments()->count();

            // Eliminar el video (cascade eliminará comentarios y análisis automáticamente)
            $video->delete();

            return response()->json([
                'success' => true,
                'message' => "Video '{$videoTitle}' eliminado correctamente con {$commentsCount} comentarios",
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar video', [
                'video_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el video',
            ], 500);
        }
    }

    /**
     * Generar buyer personas a partir de análisis de comentarios
     *
     * @param int $videoId
     * @param CommentAnalysisService $analysisService
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateBuyerPersonas($videoId, CommentAnalysisService $analysisService)
    {
        try {
            $result = $analysisService->generateBuyerPersonas($videoId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Error al generar buyer personas',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'personas' => $result['personas'],
                'metadata' => $result['metadata'],
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating buyer personas', [
                'video_id' => $videoId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar buyer personas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
