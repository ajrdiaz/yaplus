<?php

namespace App\Http\Controllers;

use App\Models\BuyerPersona;
use App\Models\CopyGeneration;
use App\Models\YoutubeBuyerPersona;
use App\Services\CopyGeneratorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CopyGeneratorController extends Controller
{
    protected $copyService;

    public function __construct(CopyGeneratorService $copyService)
    {
        $this->copyService = $copyService;
    }

    /**
     * Vista principal del generador
     */
    public function index()
    {
        // Obtener todas las buyer personas de ambas fuentes
        $googleFormPersonas = BuyerPersona::with('survey')
            ->latest()
            ->get()
            ->map(function ($persona) {
                return [
                    'id' => $persona->id,
                    'type' => 'App\Models\BuyerPersona',
                    'nombre' => $persona->nombre ?? 'Sin nombre',
                    'edad' => $persona->edad ?? 'No especificado',
                    'source' => 'Google Forms',
                    'source_name' => $persona->survey->title ?? 'Formulario',
                    'created_at' => $persona->created_at->format('Y-m-d'),
                ];
            });

        $youtubePersonas = YoutubeBuyerPersona::with('video')
            ->latest()
            ->get()
            ->map(function ($persona) {
                return [
                    'id' => $persona->id,
                    'type' => 'App\Models\YoutubeBuyerPersona',
                    'nombre' => $persona->nombre_persona,
                    'edad' => $persona->edad_rango,
                    'source' => 'YouTube',
                    'source_name' => $persona->video->title ?? 'Video',
                    'created_at' => $persona->created_at->format('Y-m-d'),
                ];
            });

        $allPersonas = $googleFormPersonas->concat($youtubePersonas);

        // Obtener tipos de copy disponibles
        $copyTypes = CopyGeneration::getCopyTypes();

        // Obtener últimos copies generados
        $recentCopies = CopyGeneration::with('buyerPersona')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($copy) {
                return [
                    'id' => $copy->id,
                    'name' => $copy->name,
                    'copy_type' => $copy->copy_type,
                    'copy_type_name' => CopyGeneration::getCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
                    'headline' => $copy->headline,
                    'created_at' => $copy->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('CopyGenerator/Index', [
            'buyerPersonas' => $allPersonas,
            'copyTypes' => $copyTypes,
            'recentCopies' => $recentCopies,
        ]);
    }

    /**
     * Generar nuevo copy
     */
    public function generate(Request $request)
    {
        $request->validate([
            'buyer_persona_id' => 'required|integer',
            'buyer_persona_type' => 'required|string',
            'copy_type' => 'required|string',
            'custom_name' => 'nullable|string|max:255',
        ]);

        try {
            // Obtener el buyer persona según el tipo
            $personaClass = $request->buyer_persona_type;
            $buyerPersona = $personaClass::findOrFail($request->buyer_persona_id);

            // Generar el copy
            $copy = $this->copyService->generateCopy(
                $buyerPersona,
                $request->copy_type,
                $request->custom_name
            );

            return response()->json([
                'success' => true,
                'copy' => [
                    'id' => $copy->id,
                    'name' => $copy->name,
                    'copy_type' => $copy->copy_type,
                    'copy_type_name' => CopyGeneration::getCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
                    'headline' => $copy->headline,
                    'subheadline' => $copy->subheadline,
                    'body' => $copy->body,
                    'cta' => $copy->cta,
                    'additional_data' => $copy->additional_data,
                    'character_count' => $copy->character_count,
                    'created_at' => $copy->created_at->diffForHumans(),
                ],
                'message' => 'Copy generado exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el copy: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de un copy
     */
    public function show($id)
    {
        $copy = CopyGeneration::with('buyerPersona')->findOrFail($id);

        return response()->json([
            'copy' => [
                'id' => $copy->id,
                'name' => $copy->name,
                'copy_type' => $copy->copy_type,
                'copy_type_name' => CopyGeneration::getCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
                'headline' => $copy->headline,
                'subheadline' => $copy->subheadline,
                'body' => $copy->body,
                'cta' => $copy->cta,
                'additional_data' => $copy->additional_data,
                'character_count' => $copy->character_count,
                'created_at' => $copy->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Eliminar un copy
     */
    public function destroy($id)
    {
        $copy = CopyGeneration::findOrFail($id);
        $copy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Copy eliminado exitosamente',
        ]);
    }

    /**
     * Historial de copies generados
     */
    public function history(Request $request)
    {
        $query = CopyGeneration::with('buyerPersona')->latest();

        // Filtros opcionales
        if ($request->has('copy_type')) {
            $query->where('copy_type', $request->copy_type);
        }

        if ($request->has('buyer_persona_type')) {
            $query->where('buyer_persona_type', $request->buyer_persona_type);
        }

        $copies = $query->paginate(20);

        return response()->json($copies);
    }
}
