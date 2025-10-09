<?php

namespace App\Services;

use App\Models\FormResponse;
use App\Models\FormResponseAnalysis;
use App\Models\FormSurvey;
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
                'Authorization' => 'Bearer ' . $this->apiKey,
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

            if (!$apiResponse->successful()) {
                Log::error('Error en API de OpenAI', [
                    'status' => $apiResponse->status(),
                    'response' => $apiResponse->body()
                ]);
                return null;
            }

            $content = $apiResponse->json('choices.0.message.content');
            
            // Limpiar markdown si existe
            $content = preg_replace('/```json\n?/', '', $content);
            $content = preg_replace('/```\n?/', '', $content);
            $content = trim($content);

            $analysisData = json_decode($content, true);

            if (!$analysisData) {
                Log::error('No se pudo parsear la respuesta de OpenAI', [
                    'response_id' => $response->id,
                    'content' => $content
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
                'error' => $e->getMessage()
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
        
        // Agregar contexto de negocio si está disponible
        if ($survey && ($survey->product_name || $survey->target_audience || $survey->research_goal)) {
            $contextInfo = "\n--- CONTEXTO DEL NEGOCIO ---\n";
            
            if ($survey->product_name) {
                $contextInfo .= "Producto/Servicio: {$survey->product_name}\n";
            }
            
            if ($survey->product_description) {
                $contextInfo .= "Descripción: {$survey->product_description}\n";
            }
            
            if ($survey->target_audience) {
                $contextInfo .= "Audiencia objetivo: {$survey->target_audience}\n";
            }
            
            if ($survey->research_goal) {
                $contextInfo .= "Objetivo de investigación: {$survey->research_goal}\n";
            }
            
            if ($survey->additional_context) {
                $contextInfo .= "Contexto adicional: {$survey->additional_context}\n";
            }
            
            $contextInfo .= "--- FIN CONTEXTO ---\n\n";
        }
        
        return $contextInfo .
               "Analiza la siguiente respuesta de encuesta:\n\n" .
               "Respuesta: {$response->combined_text}\n\n" .
               "Responde ÚNICAMENTE en formato JSON válido con esta estructura EXACTA:\n" .
               "{\n" .
               '  "category": "<UNA de estas opciones: necesidad, dolor, sueño, objecion, pregunta, experiencia_positiva, experiencia_negativa, sugerencia, otro>",' . "\n" .
               '  "sentiment": "<UNA de estas opciones: positivo, negativo, neutral>",' . "\n" .
               '  "relevance_score": <número del 1 al 10>,' . "\n" .
               '  "is_relevant": <true o false>,' . "\n" .
               '  "keywords": ["palabra1", "palabra2", "palabra3"],' . "\n" .
               '  "insights": {' . "\n" .
               '    "buyer_insight": "insight principal del buyer persona",' . "\n" .
               '    "pain_point": "punto de dolor identificado",' . "\n" .
               '    "opportunity": "oportunidad de negocio"' . "\n" .
               '  },' . "\n" .
               '  "analysis": "análisis detallado de la respuesta"' . "\n" .
               "}\n\n" .
               "IMPORTANTE: \n" .
               "- Para 'category', debes elegir SOLO UNA palabra de la lista\n" .
               "- Para 'sentiment', debes elegir SOLO UNA palabra: positivo, negativo o neutral\n" .
               "- NO uses pipes (|) ni incluyas todas las opciones\n" .
               "- Ejemplo correcto: \"category\": \"necesidad\"\n" .
               "- Ejemplo INCORRECTO: \"category\": \"necesidad|dolor|sueño\"";
    }

    /**
     * Obtener el prompt del sistema
     */
    private function getSystemPrompt(?FormSurvey $survey = null): string
    {
        $basePrompt = "Eres un experto en investigación de buyer persona y análisis de respuestas de encuestas. " .
                     "Tu objetivo es analizar respuestas de encuestas para identificar:\n" .
                     "- Necesidades: Lo que el usuario necesita o busca\n" .
                     "- Dolores: Problemas, frustraciones o dificultades\n" .
                     "- Sueños: Aspiraciones, objetivos o situación ideal\n" .
                     "- Objeciones: Razones para no comprar o dudas\n" .
                     "- Preguntas: Dudas o información que buscan\n" .
                     "- Experiencias positivas: Cosas que funcionaron bien\n" .
                     "- Experiencias negativas: Cosas que no funcionaron\n" .
                     "- Sugerencias: Ideas de mejora o características deseadas\n\n";

        if ($survey && ($survey->product_name || $survey->target_audience)) {
            $basePrompt .= "IMPORTANTE: Analiza las respuestas EN RELACIÓN al contexto específico del negocio proporcionado. ";
            $basePrompt .= "Identifica necesidades, dolores y oportunidades específicas para este producto/audiencia.\n\n";
        }

        $basePrompt .= "Analiza el sentimiento (positivo, negativo, neutral) y la relevancia (1-10) de cada respuesta. " .
                      "Extrae keywords relevantes e insights accionables para el buyer persona.";

        return $basePrompt;
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
