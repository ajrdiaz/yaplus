<?php

namespace App\Services;

use App\Models\YoutubeComment;
use App\Models\YoutubeCommentAnalysis;
use App\Models\YoutubeVideo;
use App\Models\YoutubeBuyerPersona;
use App\Models\YoutubeSalesAngle;
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

    /**
     * Generar 10 ángulos de venta basados en análisis de comentarios
     *
     * @param int $videoId
     * @return array
     */
    public function generateSalesAngles($videoId)
    {
        try {
            $video = YoutubeVideo::with('product')->findOrFail($videoId);
            
            // Obtener todos los análisis
            $analyses = YoutubeCommentAnalysis::where('youtube_video_id', $videoId)
                ->where('is_relevant', true)
                ->with('comment')
                ->orderBy('relevance_score', 'desc')
                ->take(100)
                ->get();

            if ($analyses->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No hay análisis disponibles para generar ángulos de venta',
                ];
            }

            // Obtener buyer personas existentes si los hay
            $buyerPersonas = YoutubeBuyerPersona::where('youtube_video_id', $videoId)->get();

            // Preparar datos resumidos
            $analysisData = $this->prepareAnalysisDataForAngles($analyses);
            $personasData = $this->prepareBuyerPersonasData($buyerPersonas);

            // Construir el prompt
            $prompt = $this->buildSalesAnglesPrompt($video, $analysisData, $personasData);

            // Llamar a OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto copywriter y estratega de marketing directo. Tu trabajo es crear ángulos de venta persuasivos y efectivos basados en investigación real de audiencia.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.8,
                'max_tokens' => 4000,
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API error generating sales angles', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [
                    'success' => false,
                    'message' => 'Error al generar ángulos de venta con OpenAI',
                ];
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return [
                    'success' => false,
                    'message' => 'No se recibió respuesta de OpenAI',
                ];
            }

            // Parsear JSON de la respuesta
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = preg_replace('/```json\s*/', '', $content);
                $content = preg_replace('/```\s*$/', '', $content);
            }

            $anglesData = json_decode($content, true);

            if (!$anglesData || !isset($anglesData['angles'])) {
                Log::error('Invalid JSON response from OpenAI for sales angles', [
                    'content' => $content
                ]);
                return [
                    'success' => false,
                    'message' => 'Respuesta inválida de OpenAI',
                ];
            }

            // Eliminar ángulos anteriores
            YoutubeSalesAngle::where('youtube_video_id', $videoId)->delete();

            // Guardar los nuevos ángulos
            $savedAngles = [];
            foreach ($anglesData['angles'] as $index => $angleData) {
                $angle = YoutubeSalesAngle::create([
                    'youtube_video_id' => $videoId,
                    'titulo' => $angleData['titulo'],
                    'descripcion' => $angleData['descripcion'],
                    'copy_ejemplo' => $angleData['copy_ejemplo'],
                    'enfoque' => $angleData['enfoque'] ?? null,
                    'tipo_contenido' => $angleData['tipo_contenido'] ?? null,
                    'orden' => $index + 1,
                ]);
                $savedAngles[] = $angle;
            }

            return [
                'success' => true,
                'angles' => $savedAngles,
                'metadata' => [
                    'total_angles' => count($savedAngles),
                    'based_on_analyses' => $analyses->count(),
                    'based_on_personas' => $buyerPersonas->count(),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error generating sales angles', [
                'video_id' => $videoId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error al generar ángulos de venta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Preparar datos de análisis para ángulos de venta
     */
    private function prepareAnalysisDataForAngles($analyses)
    {
        $summary = [
            'categorias' => [],
            'sentimientos' => [],
            'pain_points' => [],
            'suenos' => [],
            'objeciones' => [],
            'keywords' => [],
        ];

        foreach ($analyses as $analysis) {
            // Categorías
            $cat = $analysis->category ?? 'desconocida';
            $summary['categorias'][$cat] = ($summary['categorias'][$cat] ?? 0) + 1;

            // Sentimientos
            $sent = $analysis->sentiment ?? 'neutral';
            $summary['sentimientos'][$sent] = ($summary['sentimientos'][$sent] ?? 0) + 1;

            // Insights
            if ($analysis->insights && is_array($analysis->insights)) {
                if (isset($analysis->insights['pain_point'])) {
                    $summary['pain_points'][] = $analysis->insights['pain_point'];
                }
                if (isset($analysis->insights['opportunity'])) {
                    $summary['suenos'][] = $analysis->insights['opportunity'];
                }
            }

            // Objeciones específicas
            if ($analysis->category === 'objecion') {
                $summary['objeciones'][] = substr($analysis->ia_analysis, 0, 150);
            }

            // Keywords
            if ($analysis->keywords && is_array($analysis->keywords)) {
                $summary['keywords'] = array_merge($summary['keywords'], $analysis->keywords);
            }
        }

        // Top keywords
        $keywordCounts = array_count_values($summary['keywords']);
        arsort($keywordCounts);
        $summary['keywords'] = array_slice(array_keys($keywordCounts), 0, 20);

        // Limitar arrays
        $summary['pain_points'] = array_slice($summary['pain_points'], 0, 10);
        $summary['suenos'] = array_slice($summary['suenos'], 0, 10);
        $summary['objeciones'] = array_slice($summary['objeciones'], 0, 10);

        return $summary;
    }

    /**
     * Preparar datos de buyer personas
     */
    private function prepareBuyerPersonasData($buyerPersonas)
    {
        if ($buyerPersonas->isEmpty()) {
            return null;
        }

        return $buyerPersonas->map(function ($persona) {
            return [
                'nombre' => $persona->nombre,
                'descripcion' => $persona->descripcion,
                'motivaciones' => is_array($persona->motivaciones) ? array_slice($persona->motivaciones, 0, 3) : [],
                'pain_points' => is_array($persona->pain_points) ? array_slice($persona->pain_points, 0, 3) : [],
                'objeciones' => is_array($persona->objeciones) ? array_slice($persona->objeciones, 0, 3) : [],
            ];
        })->toArray();
    }

    /**
     * Construir prompt para generar ángulos de venta
     */
    private function buildSalesAnglesPrompt($video, $analysisData, $personasData)
    {
        $productInfo = '';
        if ($video->product) {
            $productInfo = "PRODUCTO/SERVICIO:\n";
            $productInfo .= "Nombre: {$video->product->name}\n";
            $productInfo .= "Descripción: {$video->product->description}\n";
            $productInfo .= "Propuesta de Valor: {$video->product->value_proposition}\n\n";
        }

        $personasInfo = '';
        if ($personasData) {
            $personasInfo = "BUYER PERSONAS IDENTIFICADOS:\n";
            foreach ($personasData as $i => $persona) {
                $personasInfo .= ($i + 1) . ". {$persona['nombre']}: {$persona['descripcion']}\n";
                $personasInfo .= "   - Motivaciones: " . implode(', ', $persona['motivaciones']) . "\n";
                $personasInfo .= "   - Pain Points: " . implode(', ', $persona['pain_points']) . "\n";
            }
            $personasInfo .= "\n";
        }

        return <<<PROMPT
Basándote en el análisis real de comentarios de YouTube, genera 10 ÁNGULOS DE VENTA únicos y persuasivos para crear copys de anuncios y contenido de marketing.

{$productInfo}{$personasInfo}
DATOS DEL ANÁLISIS DE AUDIENCIA:

Distribución por Categorías:
{$this->formatArrayAsString($analysisData['categorias'])}

Distribución por Sentimiento:
{$this->formatArrayAsString($analysisData['sentimientos'])}

PRINCIPALES PAIN POINTS DETECTADOS:
{$this->formatListAsString($analysisData['pain_points'])}

PRINCIPALES ASPIRACIONES/SUEÑOS:
{$this->formatListAsString($analysisData['suenos'])}

OBJECIONES PRINCIPALES:
{$this->formatListAsString($analysisData['objeciones'])}

PALABRAS CLAVE MÁS FRECUENTES:
{$this->formatListAsString($analysisData['keywords'])}

---

INSTRUCCIONES:
Genera 10 ángulos de venta ÚNICOS Y DIFERENTES entre sí. Cada ángulo debe:

1. Ser específico y accionable
2. Basarse en insights reales de la audiencia
3. Incluir un ejemplo de copy listo para usar
4. Abordar diferentes aspectos: pain points, sueños, objeciones, urgencia, prueba social, etc.
5. Variar en enfoque y tono

FORMATO REQUERIDO (JSON):
{
  "angles": [
    {
      "titulo": "Nombre corto del ángulo",
      "descripcion": "Por qué este ángulo es efectivo y a qué insight de la audiencia responde (2-3 líneas)",
      "copy_ejemplo": "Ejemplo de copy de 30-50 palabras listo para usar en anuncio o landing page",
      "enfoque": "dolor | sueño | objecion | urgencia | prueba_social | transformacion | garantia | exclusividad",
      "tipo_contenido": "anuncio | landing | email | social_media"
    }
  ]
}

CRITERIOS DE CALIDAD:
- Cada ángulo debe ser ÚNICO y no repetir el mismo enfoque
- Los copies deben ser específicos, no genéricos
- Usa las palabras clave y lenguaje de la audiencia
- Aborda los pain points y objeciones reales detectados
- Incluye variedad: algunos enfocados en dolor, otros en aspiración, otros en objeciones, etc.

RESPONDE ÚNICAMENTE CON EL JSON, SIN TEXTO ADICIONAL.
PROMPT;
    }

    /**
     * Formatear array como string
     */
    private function formatArrayAsString($array)
    {
        $result = '';
        foreach ($array as $key => $value) {
            $result .= "- {$key}: {$value}\n";
        }
        return $result;
    }

    /**
     * Formatear lista como string
     */
    private function formatListAsString($list)
    {
        if (empty($list)) {
            return "- No disponible\n";
        }
        $result = '';
        foreach ($list as $i => $item) {
            $result .= ($i + 1) . ". {$item}\n";
        }
        return $result;
    }
}
