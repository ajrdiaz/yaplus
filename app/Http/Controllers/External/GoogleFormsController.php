<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\FormResponse;
use App\Models\FormResponseAnalysis;
use App\Models\FormSurvey;
use App\Services\FormAnalysisService;
use App\Services\GoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleFormsController extends Controller
{
    /**
     * Mostrar la página principal de Google Forms
     */
    public function index()
    {
        try {
            $surveys = FormSurvey::withCount(['responses', 'analyses'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            $products = \App\Models\Product::orderBy('nombre')->get(['id', 'nombre', 'audiencia_objetivo']);

            return inertia('GoogleForms/Index', [
                'surveys' => $surveys,
                'products' => $products,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting surveys: '.$e->getMessage());

            return back()->with('error', 'Error al obtener los formularios');
        }
    }

    /**
     * Importar respuestas de Google Sheets
     */
    public function importResponses(Request $request, GoogleSheetsService $sheetsService)
    {
        $request->validate([
            'sheet_url' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'product_id' => 'required|exists:products,id',
        ]);

        try {
            // Verificar si el servicio está configurado
            if (! $sheetsService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Sheets API no está configurada. Por favor, configura las credenciales.',
                ], 400);
            }

            $sheetUrl = $request->input('sheet_url');
            $sheetId = GoogleSheetsService::extractSpreadsheetId($sheetUrl);

            if (! $sheetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL de Google Sheets inválida',
                ], 400);
            }

            // Obtener información de la hoja
            $sheetInfo = $sheetsService->getSpreadsheetInfo($sheetId);

            if (! $sheetInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo acceder a la hoja de cálculo. Verifica los permisos.',
                ], 400);
            }

            // Crear o actualizar el formulario
            $survey = FormSurvey::updateOrCreate(
                ['sheet_id' => $sheetId],
                [
                    'product_id' => $request->input('product_id'),
                    'form_id' => $sheetId, // Usamos sheet_id como form_id por ahora
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'form_url' => $sheetUrl,
                ]
            );

            // Leer respuestas de la hoja (detecta automáticamente el nombre de la hoja)
            $data = $sheetsService->readSheet($sheetId, 'A:Z');

            if (! $data || empty($data['responses'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron respuestas en la hoja. Verifica que el formulario tenga respuestas enviadas.',
                ], 400);
            }

            $imported = 0;
            $skipped = 0;

            foreach ($data['responses'] as $index => $responseData) {
                // Generar un ID único para la respuesta
                $responseId = md5($sheetId.'_'.$index.'_'.json_encode($responseData));

                // Verificar si ya existe
                if (FormResponse::where('response_id', $responseId)->exists()) {
                    $skipped++;

                    continue;
                }

                // Combinar todas las respuestas en un solo texto para análisis
                $combinedText = '';
                foreach ($responseData as $question => $answer) {
                    if (! empty($answer) && $question !== 'Timestamp' && $question !== 'Marca temporal') {
                        $combinedText .= "{$question}: {$answer}\n";
                    }
                }

                // Parsear la fecha (puede venir en varios formatos)
                $submittedAt = now();
                $timestampField = $responseData['Timestamp'] ?? $responseData['Marca temporal'] ?? null;

                if ($timestampField) {
                    try {
                        // Detectar formato de fecha
                        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})$/', $timestampField, $matches)) {
                            $day = intval($matches[1]);
                            $month = intval($matches[2]);

                            // Si el primer número es > 12, es día (formato d/m/Y)
                            // Si el segundo número es > 12, es mes (formato m/d/Y)
                            if ($day > 12) {
                                // Formato español: d/m/Y H:i:s
                                $submittedAt = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $timestampField);
                            } elseif ($month > 12) {
                                // Formato inglés: m/d/Y H:i:s
                                $submittedAt = \Carbon\Carbon::createFromFormat('m/d/Y H:i:s', $timestampField);
                            } else {
                                // Ambiguo, asumir formato español por defecto
                                $submittedAt = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $timestampField);
                            }
                        } else {
                            // Intentar parsear como ISO u otros formatos
                            $submittedAt = \Carbon\Carbon::parse($timestampField);
                        }
                    } catch (\Exception $e) {
                        Log::warning('No se pudo parsear fecha, usando fecha actual', [
                            'timestamp' => $timestampField,
                            'error' => $e->getMessage(),
                        ]);
                        $submittedAt = now();
                    }
                }

                // Crear la respuesta
                FormResponse::create([
                    'form_survey_id' => $survey->id,
                    'response_id' => $responseId,
                    'respondent_email' => $responseData['Email'] ?? $responseData['Correo electrónico'] ?? null,
                    'submitted_at' => $submittedAt,
                    'raw_data' => $responseData,
                    'combined_text' => trim($combinedText),
                ]);

                $imported++;
            }

            // Actualizar contador
            $survey->update(['responses_count' => $survey->responses()->count()]);

            return response()->json([
                'success' => true,
                'message' => "Se importaron {$imported} respuestas nuevas".($skipped > 0 ? " ({$skipped} duplicadas omitidas)" : ''),
                'data' => [
                    'survey_id' => $survey->id,
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'total' => $survey->responses_count,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al importar respuestas de Google Forms', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al importar respuestas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener respuestas de un formulario
     */
    public function getSurveyResponses($surveyId)
    {
        try {
            $survey = FormSurvey::with(['responses' => function ($query) {
                $query->orderBy('submitted_at', 'desc');
            }])->findOrFail($surveyId);

            return response()->json([
                'success' => true,
                'survey' => $survey,
                'responses' => $survey->responses,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener respuestas',
            ], 500);
        }
    }

    /**
     * Analizar respuestas con IA
     */
    public function analyzeResponses(Request $request, FormAnalysisService $analysisService)
    {
        $request->validate([
            'survey_id' => 'required|exists:form_surveys,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $surveyId = $request->input('survey_id');
        $limit = $request->input('limit', null);

        try {
            $results = $analysisService->analyzeSurveyResponses($surveyId, $limit);

            return response()->json([
                'success' => true,
                'message' => "Análisis completado: {$results['analyzed']} respuestas analizadas",
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al analizar respuestas', [
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al analizar respuestas con IA',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener análisis de un formulario
     */
    public function getAnalysis($surveyId, FormAnalysisService $analysisService)
    {
        try {
            $survey = FormSurvey::with('responses', 'buyerPersonas')->findOrFail($surveyId);

            $analyses = FormResponseAnalysis::with('response')
                ->where('form_survey_id', $surveyId)
                ->orderBy('relevance_score', 'desc')
                ->get();

            $stats = $analysisService->getSurveyAnalysisStats($surveyId);

            return inertia('GoogleForms/Analysis', [
                'survey' => $survey,
                'analyses' => $analyses,
                'stats' => $stats,
                'existingPersonas' => $survey->buyerPersonas,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener análisis', [
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error al obtener análisis');
        }
    }

    /**
     * Generar Buyer Personas
     */
    public function generateBuyerPersonas($surveyId, FormAnalysisService $analysisService)
    {
        try {
            $result = $analysisService->generateBuyerPersonas($surveyId);

            if (! $result['success']) {
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
                'survey_id' => $surveyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar buyer personas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un formulario con todas sus respuestas y análisis
     */
    public function destroy($id)
    {
        try {
            $survey = FormSurvey::findOrFail($id);

            $surveyTitle = $survey->title;
            $responsesCount = $survey->responses()->count();

            // Eliminar (cascade eliminará respuestas y análisis automáticamente)
            $survey->delete();

            return response()->json([
                'success' => true,
                'message' => "Formulario '{$surveyTitle}' eliminado correctamente con {$responsesCount} respuestas",
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar formulario', [
                'survey_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el formulario',
            ], 500);
        }
    }
}
