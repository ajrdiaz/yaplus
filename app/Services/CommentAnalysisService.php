<?php

namespace App\Services;

use App\Models\YoutubeComment;
use App\Models\YoutubeCommentAnalysis;
use App\Models\YoutubeVideo;
use App\Models\YoutubeBuyerPersona;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CommentAnalysisService
{
    private ?string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini'); // Modelo configurable desde .env
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
        $query = YoutubeComment::with('video.product')
            ->where('youtube_video_id', $videoId)
            ->whereDoesntHave('analysis')
            ->whereRaw('LENGTH(text_original) > 20'); // Filtrar comentarios muy cortos

        if ($limit) {
            $query->limit($limit);
        }

        $comments = $query->get();
        $analyzed = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($comments as $comment) {
            // Doble verificación por si acaso
            if (strlen($comment->text_original) <= 20) {
                $skipped++;
                continue;
            }

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
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Construir el prompt para el análisis
     */
    private function buildPrompt(YoutubeComment $comment): string
    {
        $video = $comment->video;
        $contextInfo = '';

        // Agregar contexto de negocio desde el producto asociado
        if ($video && $video->product) {
            $product = $video->product;
            $contextInfo = "\n--- CONTEXTO DEL NEGOCIO ---\n";

            if ($product->nombre) {
                $contextInfo .= "Producto/Servicio: {$product->nombre}\n";
            }

            if ($product->descripcion) {
                $contextInfo .= "Descripción: {$product->descripcion}\n";
            }

            if ($product->audiencia_objetivo) {
                $contextInfo .= "Audiencia objetivo: {$product->audiencia_objetivo}\n";
            }

            if ($product->puntos_dolor) {
                $contextInfo .= "Puntos de dolor conocidos: {$product->puntos_dolor}\n";
            }

            if ($product->beneficios_clave) {
                $contextInfo .= "Beneficios clave: {$product->beneficios_clave}\n";
            }

            $contextInfo .= "--- FIN CONTEXTO ---\n\n";
        }

        return $contextInfo .
               "Analiza el siguiente comentario de YouTube:\n\n" .
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
               '    "buyer_insight": "Qué revela sobre el buyer persona para este producto específico",' . "\n" .
               '    "pain_point": "Punto de dolor específico relacionado con el producto/audiencia",' . "\n" .
               '    "opportunity": "Oportunidad de negocio específica para este contexto"' . "\n" .
               '  },' . "\n" .
               '  "analysis": "Análisis del comentario EN RELACIÓN al producto/audiencia mencionada"' . "\n" .
               "}";
    }

    /**
     * Prompt del sistema
     */
    private function getSystemPrompt(): string
    {
        return "Eres un experto en análisis de buyer persona y customer research. " .
               "Tu trabajo es analizar comentarios de YouTube para identificar insights valiosos.\n\n" .
               "IMPORTANTE: Si se proporciona contexto del negocio (producto, audiencia, objetivo), " .
               "debes analizar el comentario EN RELACIÓN a ese contexto específico.\n\n" .
               "Categorías de análisis:\n" .
               "1. NECESIDADES: Qué necesita el usuario, qué busca\n" .
               "2. DOLORES: Problemas, frustraciones, quejas\n" .
               "3. SUEÑOS: Aspiraciones, deseos, objetivos\n" .
               "4. OBJECIONES: Razones para no comprar, dudas, preocupaciones\n" .
               "5. PREGUNTAS: Dudas específicas sobre el producto/servicio\n" .
               "6. EXPERIENCIAS: Positivas o negativas con productos similares\n" .
               "7. SUGERENCIAS: Ideas de mejora o nuevas features\n\n" .
               "Evalúa la relevancia del comentario (1-10) considerando:\n" .
               "- ¿Qué tan útil es para entender al buyer persona del producto específico?\n" .
               "- ¿Revela información accionable para el objetivo de investigación?\n\n" .
               "Marca 'is_relevant: true' solo si el comentario tiene información valiosa " .
               "para el contexto de negocio proporcionado.\n\n" .
               "Extrae keywords importantes relacionadas con el producto/audiencia objetivo.\n" .
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

    /**
     * Generar buyer personas basados en análisis de comentarios de YouTube
     *
     * @param int $videoId
     * @param int $numPersonas
     * @return array
     */
    public function generateBuyerPersonas($videoId, $numPersonas = 4)
    {
        try {
            $video = YoutubeVideo::with('product')->findOrFail($videoId);
            
            // Obtener todos los análisis con sus comentarios
            $allAnalyses = YoutubeCommentAnalysis::where('youtube_video_id', $videoId)
                ->with('comment')
                ->get();

            if ($allAnalyses->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No hay análisis disponibles para generar buyer personas',
                ];
            }

            // Seleccionar muestra representativa para evitar exceder límite de tokens
            // Priorizar: relevantes + variedad de categorías + diferentes sentimientos
            $analyses = collect();
            
            // 1. Tomar los 20 más relevantes
            $topRelevant = $allAnalyses->where('is_relevant', true)
                ->sortByDesc('relevance_score')
                ->take(20);
            $analyses = $analyses->merge($topRelevant);
            
            // 2. Tomar muestra de cada categoría (5 por categoría)
            $categories = $allAnalyses->pluck('category')->unique();
            foreach ($categories as $category) {
                $categoryAnalyses = $allAnalyses->where('category', $category)
                    ->sortByDesc('relevance_score')
                    ->take(5);
                $analyses = $analyses->merge($categoryAnalyses);
            }
            
            // 3. Asegurar diversidad de sentimientos (5 por sentimiento)
            $sentiments = ['positivo', 'neutral', 'negativo'];
            foreach ($sentiments as $sentiment) {
                $sentimentAnalyses = $allAnalyses->where('sentiment', $sentiment)
                    ->sortByDesc('relevance_score')
                    ->take(5);
                $analyses = $analyses->merge($sentimentAnalyses);
            }
            
            // Eliminar duplicados y limitar a máximo 80 análisis
            $analyses = $analyses->unique('id')->take(80);

            // Preparar datos para la IA (solo lo esencial)
            $analysisData = $analyses->map(function ($analysis) {
                return [
                    'category' => $analysis->category,
                    'sentiment' => $analysis->sentiment,
                    'relevance_score' => $analysis->relevance_score,
                    'keywords' => is_array($analysis->keywords) ? array_slice($analysis->keywords, 0, 5) : [],
                    'insights' => $analysis->insights,
                    // Limitar análisis a 200 caracteres
                    'analysis' => substr($analysis->ia_analysis, 0, 200),
                ];
            })->toArray();

            // Construir el prompt
            $prompt = $this->buildBuyerPersonaPrompt($video, $analysisData, $numPersonas);

            // Llamar a OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto en marketing y análisis de buyer personas. Tu trabajo es identificar patrones en los datos y crear perfiles de cliente ideal detallados y accionables.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error generating buyer personas', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'message' => 'Error al generar buyer personas con OpenAI',
                ];
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return [
                    'success' => false,
                    'message' => 'No se pudo obtener respuesta de OpenAI',
                ];
            }

            // Limpiar y parsear JSON
            $content = trim($content);
            $content = preg_replace('/^```json\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            
            $personas = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Error parsing buyer personas JSON', [
                    'error' => json_last_error_msg(),
                    'content' => $content
                ]);
                return [
                    'success' => false,
                    'message' => 'Error al procesar la respuesta de OpenAI',
                ];
            }

            $personasArray = $personas['personas'] ?? $personas;

            // Guardar en base de datos
            DB::transaction(function () use ($video, $personasArray, $allAnalyses) {
                // Eliminar buyer personas anteriores de este video
                YoutubeBuyerPersona::where('youtube_video_id', $video->id)->delete();

                // Guardar los nuevos buyer personas
                foreach ($personasArray as $personaData) {
                    YoutubeBuyerPersona::create([
                        'youtube_video_id' => $video->id,
                        'nombre' => $personaData['nombre'] ?? '',
                        'edad' => $personaData['edad'] ?? null,
                        'ocupacion' => $personaData['ocupacion'] ?? null,
                        'descripcion' => $personaData['descripcion'] ?? null,
                        'motivaciones' => $personaData['motivaciones'] ?? [],
                        'pain_points' => $personaData['pain_points'] ?? [],
                        'suenos' => $personaData['suenos'] ?? [],
                        'objeciones' => $personaData['objeciones'] ?? [],
                        'comportamiento' => $personaData['comportamiento'] ?? null,
                        'canales_preferidos' => $personaData['canales_preferidos'] ?? [],
                        'keywords_clave' => $personaData['keywords_clave'] ?? [],
                        'porcentaje_audiencia' => $personaData['porcentaje_audiencia'] ?? 0,
                        'nivel_prioridad' => $personaData['nivel_prioridad'] ?? 'media',
                        'estrategia_recomendada' => $personaData['estrategia_recomendada'] ?? null,
                        'total_comments_analyzed' => $allAnalyses->count(),
                    ]);
                }
            });

            // Recargar buyer personas creados
            $createdPersonas = YoutubeBuyerPersona::where('youtube_video_id', $video->id)->get();

            return [
                'success' => true,
                'personas' => $createdPersonas,
                'metadata' => [
                    'total_comments_analyzed' => $analyses->count(),
                    'num_personas' => count($personasArray),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error generating buyer personas', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error al generar buyer personas: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Construir el prompt para generar buyer personas
     *
     * @param YoutubeVideo $video
     * @param array $analysisData
     * @param int $numPersonas
     * @return string
     */
    private function buildBuyerPersonaPrompt(YoutubeVideo $video, array $analysisData, int $numPersonas): string
    {
        $contextInfo = "Video: {$video->title}\n";
        $contextInfo .= "Canal: {$video->channel_title}\n";
        $contextInfo .= "Total comentarios analizados: " . count($analysisData) . "\n";

        // Agregar contexto desde el producto asociado
        if ($video->product) {
            $product = $video->product;
            $contextInfo .= "\nCONTEXTO DEL PRODUCTO:\n";
            $contextInfo .= "Producto: {$product->nombre}\n";

            if ($product->descripcion) {
                $contextInfo .= "Descripción: {$product->descripcion}\n";
            }

            if ($product->audiencia_objetivo) {
                $contextInfo .= "Audiencia objetivo: {$product->audiencia_objetivo}\n";
            }

            if ($product->puntos_dolor) {
                $contextInfo .= "Puntos de dolor conocidos: {$product->puntos_dolor}\n";
            }

            if ($product->beneficios_clave) {
                $contextInfo .= "Beneficios clave: {$product->beneficios_clave}\n";
            }
        }

        // Crear resumen estadístico en lugar de enviar todo el JSON
        $summary = $this->createAnalysisSummary($analysisData);

        return <<<PROMPT
{$contextInfo}

RESUMEN DE ANÁLISIS DE COMENTARIOS:
{$summary}

TAREA: Crea {$numPersonas} buyer personas distintos basándote en estos patrones.

INSTRUCCIONES:
1. Analiza los patrones en los comentarios: motivaciones, pain points, sueños, objeciones
2. Identifica segmentos claros de audiencia basados en comportamientos y necesidades similares
3. Crea {$numPersonas} buyer personas únicos y diferenciados
4. Para cada buyer persona, estima el porcentaje de la audiencia que representa (deben sumar 100%)
5. Asigna un nivel de prioridad (alta, media, baja) basado en relevancia y potencial

FORMATO DE RESPUESTA (JSON):
Devuelve un array JSON con la siguiente estructura EXACTA:

{
  "personas": [
    {
      "nombre": "Nombre del Persona (ej: María Emprendedora)",
      "edad": "Rango de edad (ej: 25-35 años)",
      "ocupacion": "Ocupación/Rol (ej: Emprendedora digital)",
      "descripcion": "Descripción breve de quién es este persona",
      "motivaciones": ["motivación 1", "motivación 2", "motivación 3"],
      "pain_points": ["dolor 1", "dolor 2", "dolor 3"],
      "suenos": ["sueño 1", "sueño 2", "sueño 3"],
      "objeciones": ["objeción 1", "objeción 2"],
      "comportamiento": "Descripción de su comportamiento de compra/decisión",
      "canales_preferidos": ["canal 1", "canal 2", "canal 3"],
      "keywords_clave": ["keyword 1", "keyword 2", "keyword 3"],
      "porcentaje_audiencia": 40,
      "nivel_prioridad": "alta",
      "estrategia_recomendada": "Estrategia específica para este segmento"
    }
  ]
}

RESPONDE ÚNICAMENTE CON EL JSON, SIN TEXTO ADICIONAL.
PROMPT;
    }

    /**
     * Crear un resumen compacto de los análisis para el prompt
     *
     * @param array $analysisData
     * @return string
     */
    private function createAnalysisSummary(array $analysisData): string
    {
        $categories = [];
        $sentiments = [];
        $allKeywords = [];
        $allInsights = [];

        foreach ($analysisData as $analysis) {
            // Contar categorías
            $cat = $analysis['category'] ?? 'desconocida';
            $categories[$cat] = ($categories[$cat] ?? 0) + 1;

            // Contar sentimientos
            $sent = $analysis['sentiment'] ?? 'neutral';
            $sentiments[$sent] = ($sentiments[$sent] ?? 0) + 1;

            // Acumular keywords
            if (isset($analysis['keywords']) && is_array($analysis['keywords'])) {
                $allKeywords = array_merge($allKeywords, $analysis['keywords']);
            }

            // Acumular insights relevantes
            if (isset($analysis['insights']) && is_array($analysis['insights'])) {
                foreach ($analysis['insights'] as $key => $value) {
                    if ($value && strlen($value) > 20) {
                        $allInsights[] = substr($value, 0, 100);
                    }
                }
            }
        }

        // Top keywords
        $keywordCounts = array_count_values($allKeywords);
        arsort($keywordCounts);
        $topKeywords = array_slice(array_keys($keywordCounts), 0, 15);

        $summary = "DISTRIBUCIÓN:\n";
        $summary .= "- Categorías: " . json_encode($categories) . "\n";
        $summary .= "- Sentimientos: " . json_encode($sentiments) . "\n";
        $summary .= "- Top Keywords: " . implode(", ", $topKeywords) . "\n\n";
        
        $summary .= "INSIGHTS PRINCIPALES (muestra):\n";
        $sampleInsights = array_slice($allInsights, 0, 10);
        foreach ($sampleInsights as $i => $insight) {
            $summary .= ($i + 1) . ". " . $insight . "\n";
        }

        return $summary;
    }
}
