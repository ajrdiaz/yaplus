<?php

namespace App\Services;

use App\Models\YoutubeComment;
use App\Models\YoutubeCommentAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommentAnalysisService
{
    private ?string $apiKey;
    private string $model = 'gpt-4o-mini'; // Modelo más económico y rápido

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    /**
     * Analizar un comentario individual
     */
    public function analyzeComment(YoutubeComment $comment): ?YoutubeCommentAnalysis
    {
        if (!$this->apiKey) {
            Log::warning('OpenAI API key no configurada');
            return null;
        }

        try {
            $prompt = $this->buildPrompt($comment);
            
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt()
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);

            if (!$response->successful()) {
                Log::error('Error en OpenAI API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return null;
            }

            return $this->saveAnalysis($comment, $content, $data['usage']['total_tokens'] ?? 0);

        } catch (\Exception $e) {
            Log::error('Error al analizar comentario', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Analizar múltiples comentarios de un video
     */
    public function analyzeVideoComments($videoId, $limit = null)
    {
        $query = YoutubeComment::where('youtube_video_id', $videoId)
            ->whereDoesntHave('analysis');

        if ($limit) {
            $query->limit($limit);
        }

        $comments = $query->get();
        $analyzed = 0;
        $errors = 0;

        foreach ($comments as $comment) {
            $analysis = $this->analyzeComment($comment);
            
            if ($analysis) {
                $analyzed++;
            } else {
                $errors++;
            }

            // Pausa para no saturar la API
            usleep(500000); // 0.5 segundos
        }

        return [
            'total' => $comments->count(),
            'analyzed' => $analyzed,
            'errors' => $errors,
        ];
    }

    /**
     * Construir el prompt para el análisis
     */
    private function buildPrompt(YoutubeComment $comment): string
    {
        return "Analiza el siguiente comentario de YouTube:\n\n" .
               "Autor: {$comment->author}\n" .
               "Comentario: {$comment->text_original}\n" .
               "Likes: {$comment->like_count}\n\n" .
               "Responde ÚNICAMENTE en formato JSON con esta estructura:\n" .
               "{\n" .
               '  "category": "necesidad|dolor|sueño|objecion|pregunta|experiencia_positiva|experiencia_negativa|sugerencia|otro",' . "\n" .
               '  "sentiment": "positivo|negativo|neutral",' . "\n" .
               '  "relevance_score": 1-10,' . "\n" .
               '  "is_relevant": true|false,' . "\n" .
               '  "keywords": ["palabra1", "palabra2", ...],' . "\n" .
               '  "insights": {' . "\n" .
               '    "buyer_insight": "Qué revela sobre el buyer persona",' . "\n" .
               '    "pain_point": "Punto de dolor específico si aplica",' . "\n" .
               '    "opportunity": "Oportunidad de negocio si la hay"' . "\n" .
               '  },' . "\n" .
               '  "analysis": "Análisis breve del comentario"' . "\n" .
               "}";
    }

    /**
     * Prompt del sistema
     */
    private function getSystemPrompt(): string
    {
        return "Eres un experto en análisis de buyer persona y customer research. " .
               "Tu trabajo es analizar comentarios de YouTube para identificar:\n\n" .
               "1. NECESIDADES: Qué necesita el usuario, qué busca\n" .
               "2. DOLORES: Problemas, frustraciones, quejas\n" .
               "3. SUEÑOS: Aspiraciones, deseos, objetivos\n" .
               "4. OBJECIONES: Razones para no comprar, dudas, preocupaciones\n" .
               "5. PREGUNTAS: Dudas específicas sobre el producto/servicio\n" .
               "6. EXPERIENCIAS: Positivas o negativas con productos similares\n" .
               "7. SUGERENCIAS: Ideas de mejora o nuevas features\n\n" .
               "Evalúa la relevancia del comentario para investigación de mercado (1-10).\n" .
               "Marca como 'is_relevant: true' solo si el comentario tiene información valiosa para el buyer persona.\n" .
               "Extrae keywords importantes relacionadas con el negocio.\n" .
               "Responde SIEMPRE en formato JSON válido.";
    }

    /**
     * Guardar el análisis en la base de datos
     */
    private function saveAnalysis(YoutubeComment $comment, string $aiResponse, int $tokensUsed): ?YoutubeCommentAnalysis
    {
        try {
            // Intentar parsear la respuesta JSON
            $analysis = json_decode($aiResponse, true);

            if (!$analysis) {
                Log::error('No se pudo parsear respuesta de IA', ['response' => $aiResponse]);
                return null;
            }

            return YoutubeCommentAnalysis::create([
                'youtube_comment_id' => $comment->id,
                'youtube_video_id' => $comment->youtube_video_id,
                'category' => $analysis['category'] ?? 'otro',
                'sentiment' => $analysis['sentiment'] ?? 'neutral',
                'relevance_score' => $analysis['relevance_score'] ?? 5,
                'is_relevant' => $analysis['is_relevant'] ?? false,
                'keywords' => $analysis['keywords'] ?? [],
                'insights' => $analysis['insights'] ?? [],
                'ia_analysis' => $analysis['analysis'] ?? '',
                'ai_model' => $this->model,
                'tokens_used' => $tokensUsed,
                'analyzed_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar análisis', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtener estadísticas de análisis de un video
     */
    public function getVideoAnalysisStats($videoId)
    {
        $analyses = YoutubeCommentAnalysis::where('youtube_video_id', $videoId)->get();

        return [
            'total_analyzed' => $analyses->count(),
            'relevant_count' => $analyses->where('is_relevant', true)->count(),
            'by_category' => $analyses->groupBy('category')->map->count(),
            'by_sentiment' => $analyses->groupBy('sentiment')->map->count(),
            'avg_relevance' => round($analyses->avg('relevance_score'), 2),
            'top_keywords' => $this->extractTopKeywords($analyses),
        ];
    }

    /**
     * Extraer las palabras clave más frecuentes
     */
    private function extractTopKeywords($analyses, $limit = 10)
    {
        $allKeywords = [];

        foreach ($analyses as $analysis) {
            if ($analysis->keywords) {
                foreach ($analysis->keywords as $keyword) {
                    $allKeywords[] = strtolower($keyword);
                }
            }
        }

        $keywordCounts = array_count_values($allKeywords);
        arsort($keywordCounts);

        return array_slice($keywordCounts, 0, $limit, true);
    }
}
