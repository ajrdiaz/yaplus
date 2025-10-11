<?php

namespace App\Services;

use App\Models\BuyerPersona;
use App\Models\FormResponse;
use App\Models\FormResponseAnalysis;
use App\Models\FormSurvey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FormAnalysisService
{
    private $apiKey;

    private $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }

    /**
     * Analizar todas las respuestas de un formulario
     */
    public function analyzeSurveyResponses($surveyId, $limit = null, $progressCallback = null)
    {
        $query = FormResponse::where('form_survey_id', $surveyId)
            ->with('survey.product')
            ->whereDoesntHave('analysis')
            ->whereRaw('LENGTH(combined_text) > 20'); // Filtrar respuestas muy cortas

        if ($limit) {
            $query->limit($limit);
        }

        $responses = $query->get();
        $analyzed = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($responses as $response) {
            // Doble verificación
            if (strlen($response->combined_text) <= 20) {
                $skipped++;

                continue;
            }

            $analysis = $this->analyzeResponse($response);

            if ($analysis) {
                $analyzed++;
            } else {
                $errors++;
            }

            // Llamar al callback de progreso si existe
            if ($progressCallback && is_callable($progressCallback)) {
                $progressCallback();
            }

            // Pausa para no saturar la API
            usleep(500000); // 0.5 segundos
        }

        return [
            'total' => $responses->count(),
            'analyzed' => $analyzed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Analizar una respuesta individual con IA
     */
    public function analyzeResponse(FormResponse $response): ?FormResponseAnalysis
    {
        try {
            $prompt = $this->buildPrompt($response);
            $systemPrompt = $this->getSystemPrompt($response->survey);

            $apiResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            if (! $apiResponse->successful()) {
                Log::error('Error en API de OpenAI', [
                    'status' => $apiResponse->status(),
                    'response' => $apiResponse->body(),
                ]);

                return null;
            }

            $content = $apiResponse->json('choices.0.message.content');

            // Limpiar markdown si existe
            $content = preg_replace('/```json\n?/', '', $content);
            $content = preg_replace('/```\n?/', '', $content);
            $content = trim($content);

            $analysisData = json_decode($content, true);

            if (! $analysisData) {
                Log::error('No se pudo parsear la respuesta de OpenAI', [
                    'response_id' => $response->id,
                    'content' => $content,
                ]);

                return null;
            }

            // Crear el análisis
            return FormResponseAnalysis::create([
                'form_response_id' => $response->id,
                'form_survey_id' => $response->form_survey_id,
                'category' => $analysisData['category'] ?? 'otro',
                'sentiment' => $analysisData['sentiment'] ?? 'neutral',
                'relevance_score' => $analysisData['relevance_score'] ?? 5,
                'is_relevant' => $analysisData['is_relevant'] ?? false,
                'ia_analysis' => $analysisData['analysis'] ?? '',
                'keywords' => $analysisData['keywords'] ?? [],
                'insights' => $analysisData['insights'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al analizar respuesta de formulario', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Construir el prompt para el análisis
     */
    private function buildPrompt(FormResponse $response): string
    {
        $survey = $response->survey;
        $contextInfo = '';

        // Agregar contexto de negocio desde el producto asociado
        if ($survey && $survey->product) {
            $product = $survey->product;
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

        return $contextInfo.
               "Analiza la siguiente respuesta de encuesta:\n\n".
               "Respuesta: {$response->combined_text}\n\n".
               "Responde ÚNICAMENTE en formato JSON válido con esta estructura EXACTA:\n".
               "{\n".
               '  "category": "<UNA de estas opciones: necesidad, dolor, sueño, objecion, pregunta, experiencia_positiva, experiencia_negativa, sugerencia, otro>",'."\n".
               '  "sentiment": "<UNA de estas opciones: positivo, negativo, neutral>",'."\n".
               '  "relevance_score": <número del 1 al 10>,'."\n".
               '  "is_relevant": <true o false>,'."\n".
               '  "keywords": ["palabra1", "palabra2", "palabra3"],'."\n".
               '  "insights": {'."\n".
               '    "buyer_insight": "insight principal del buyer persona",'."\n".
               '    "pain_point": "punto de dolor identificado",'."\n".
               '    "opportunity": "oportunidad de negocio"'."\n".
               '  },'."\n".
               '  "analysis": "análisis detallado de la respuesta"'."\n".
               "}\n\n".
               "IMPORTANTE: \n".
               "- Para 'category', debes elegir SOLO UNA palabra de la lista\n".
               "- Para 'sentiment', debes elegir SOLO UNA palabra: positivo, negativo o neutral\n".
               "- NO uses pipes (|) ni incluyas todas las opciones\n".
               "- Ejemplo correcto: \"category\": \"necesidad\"\n".
               '- Ejemplo INCORRECTO: "category": "necesidad|dolor|sueño"';
    }

    /**
     * Obtener el prompt del sistema
     */
    private function getSystemPrompt(?FormSurvey $survey = null): string
    {
        $basePrompt = 'Eres un experto en investigación de buyer persona y análisis de respuestas de encuestas. '.
                     "Tu objetivo es analizar respuestas de encuestas para identificar:\n".
                     "- Necesidades: Lo que el usuario necesita o busca\n".
                     "- Dolores: Problemas, frustraciones o dificultades\n".
                     "- Sueños: Aspiraciones, objetivos o situación ideal\n".
                     "- Objeciones: Razones para no comprar o dudas\n".
                     "- Preguntas: Dudas o información que buscan\n".
                     "- Experiencias positivas: Cosas que funcionaron bien\n".
                     "- Experiencias negativas: Cosas que no funcionaron\n".
                     "- Sugerencias: Ideas de mejora o características deseadas\n\n";

        if ($survey && $survey->product) {
            $basePrompt .= 'IMPORTANTE: Analiza las respuestas EN RELACIÓN al contexto específico del negocio proporcionado. ';
            $basePrompt .= "Identifica necesidades, dolores y oportunidades específicas para este producto/audiencia.\n\n";
        }

        $basePrompt .= 'Analiza el sentimiento (positivo, negativo, neutral) y la relevancia (1-10) de cada respuesta. '.
                      'Extrae keywords relevantes e insights accionables para el buyer persona.';

        return $basePrompt;
    }

    /**
     * Generar Buyer Personas basado en los análisis
     */
    public function generateBuyerPersonas($surveyId, $numPersonas = 4)
    {
        try {
            $survey = FormSurvey::with('product')->findOrFail($surveyId);

            // Obtener todos los análisis con sus respuestas
            $analyses = FormResponseAnalysis::whereHas('response', function ($query) use ($surveyId) {
                $query->where('form_survey_id', $surveyId);
            })
                ->with('response')
                ->get();

            if ($analyses->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No hay análisis disponibles para generar buyer personas',
                ];
            }

            // Preparar datos para la IA
            $analysisData = $analyses->map(function ($analysis) {
                return [
                    'category' => $analysis->category,
                    'sentiment' => $analysis->sentiment,
                    'relevance_score' => $analysis->relevance_score,
                    'keywords' => $analysis->keywords,
                    'insights' => $analysis->insights,
                    'analysis' => $analysis->ia_analysis,
                ];
            })->toArray();

            // Construir el prompt
            $prompt = $this->buildBuyerPersonaPrompt($survey, $analysisData, $numPersonas);

            // Llamar a OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto en marketing y análisis de buyer personas. Tu trabajo es identificar patrones en los datos y crear perfiles de cliente ideal detallados y accionables.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000,
            ]);

            if (! $response->successful()) {
                Log::error('OpenAI API error generating buyer personas', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Error al generar buyer personas con OpenAI',
                ];
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? null;

            if (! $content) {
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
                    'content' => $content,
                ]);

                return [
                    'success' => false,
                    'message' => 'Error al procesar la respuesta de OpenAI',
                ];
            }

            $personasArray = $personas['personas'] ?? $personas;

            // Guardar en base de datos
            DB::transaction(function () use ($survey, $personasArray, $analyses) {
                // Eliminar buyer personas anteriores de este survey
                $survey->buyerPersonas()->delete();

                // Guardar los nuevos buyer personas
                foreach ($personasArray as $personaData) {
                    BuyerPersona::create([
                        'form_survey_id' => $survey->id,
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
                        'total_responses_analyzed' => $analyses->count(),
                    ]);
                }
            });

            return [
                'success' => true,
                'personas' => $personasArray,
                'metadata' => [
                    'total_responses' => $analyses->count(),
                    'generated_at' => now()->toIso8601String(),
                    'saved_to_database' => true,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Error generating buyer personas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al generar buyer personas: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Construir prompt para generar buyer personas
     */
    private function buildBuyerPersonaPrompt($survey, $analysisData, $numPersonas)
    {
        $contextInfo = "CONTEXTO DEL NEGOCIO:\n";
        $contextInfo .= "Formulario: {$survey->form_title}\n";

        // Agregar contexto desde el producto asociado
        if ($survey->product) {
            $product = $survey->product;

            if ($product->nombre) {
                $contextInfo .= "Producto: {$product->nombre}\n";
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
        }

        $contextInfo .= "\n";

        // Estadísticas generales
        $categoryCounts = [];
        $sentimentCounts = [];
        $allKeywords = [];

        foreach ($analysisData as $item) {
            $cat = $item['category'] ?? 'otro';
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;

            $sent = $item['sentiment'] ?? 'neutral';
            $sentimentCounts[$sent] = ($sentimentCounts[$sent] ?? 0) + 1;

            if (! empty($item['keywords'])) {
                $allKeywords = array_merge($allKeywords, $item['keywords']);
            }
        }

        $statsInfo = "ESTADÍSTICAS GENERALES:\n";
        $statsInfo .= 'Total de respuestas analizadas: '.count($analysisData)."\n";
        $statsInfo .= 'Categorías: '.json_encode($categoryCounts)."\n";
        $statsInfo .= 'Sentimientos: '.json_encode($sentimentCounts)."\n";
        $statsInfo .= "\n";

        // Datos de análisis (resumidos)
        $analysisInfo = "DATOS DE ANÁLISIS:\n";
        $analysisInfo .= json_encode($analysisData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $analysisInfo .= "\n\n";

        return $contextInfo.$statsInfo.$analysisInfo.
               "TAREA:\n".
               "Analiza todos los datos anteriores y genera EXACTAMENTE {$numPersonas} buyer personas diferentes.\n".
               "Cada buyer persona debe representar un segmento significativo de la audiencia.\n\n".
               "Responde ÚNICAMENTE en formato JSON válido con esta estructura:\n".
               "{\n".
               '  "personas": ['."\n".
               '    {'."\n".
               '      "nombre": "Nombre representativo",'."\n".
               '      "edad": "Rango de edad (ej: 25-35)",'."\n".
               '      "ocupacion": "Ocupación principal",'."\n".
               '      "descripcion": "Descripción breve del perfil",'."\n".
               '      "motivaciones": ["motivación 1", "motivación 2", "motivación 3"],'."\n".
               '      "pain_points": ["dolor 1", "dolor 2", "dolor 3"],'."\n".
               '      "suenos": ["sueño 1", "sueño 2"],'."\n".
               '      "objeciones": ["objeción 1", "objeción 2"],'."\n".
               '      "comportamiento": "Descripción de su comportamiento de compra",'."\n".
               '      "canales_preferidos": ["canal 1", "canal 2"],'."\n".
               '      "keywords_clave": ["palabra 1", "palabra 2", "palabra 3"],'."\n".
               '      "porcentaje_audiencia": 25,'."\n".
               '      "nivel_prioridad": "alta|media|baja",'."\n".
               '      "estrategia_recomendada": "Cómo abordar a este segmento"'."\n".
               '    }'."\n".
               '  ]'."\n".
               "}\n\n".
               "IMPORTANTE:\n".
               "- Crea perfiles DISTINTOS y bien diferenciados\n".
               "- Basa cada perfil en patrones reales de los datos\n".
               "- Los porcentajes de audiencia deben sumar aproximadamente 100\n".
               "- Ordena por nivel de prioridad (alta primero)\n".
               '- Sé específico y accionable en las estrategias';
    }

    /**
     * Obtener estadísticas de análisis de un formulario
     */
    public function getSurveyAnalysisStats($surveyId): array
    {
        $analyses = FormResponseAnalysis::where('form_survey_id', $surveyId)->get();

        if ($analyses->isEmpty()) {
            return [
                'total_analyzed' => 0,
                'relevant_count' => 0,
                'avg_relevance' => 0,
                'by_category' => [],
                'by_sentiment' => [],
                'top_keywords' => [],
            ];
        }

        // Keywords más frecuentes
        $allKeywords = [];
        foreach ($analyses as $analysis) {
            if ($analysis->keywords) {
                foreach ($analysis->keywords as $keyword) {
                    $keyword = strtolower($keyword);
                    $allKeywords[$keyword] = ($allKeywords[$keyword] ?? 0) + 1;
                }
            }
        }
        arsort($allKeywords);
        $topKeywords = array_slice($allKeywords, 0, 10, true);

        return [
            'total_analyzed' => $analyses->count(),
            'relevant_count' => $analyses->where('is_relevant', true)->count(),
            'avg_relevance' => round($analyses->avg('relevance_score'), 1),
            'by_category' => $analyses->groupBy('category')->map->count()->toArray(),
            'by_sentiment' => $analyses->groupBy('sentiment')->map->count()->toArray(),
            'top_keywords' => $topKeywords,
        ];
    }
}
