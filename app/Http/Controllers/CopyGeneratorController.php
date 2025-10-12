<?php

namespace App\Http\Controllers;

use App\Models\CopyGeneration;
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
        // Obtener productos con datos consolidados
        $products = \App\Models\Product::orderBy('nombre')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'nombre' => $product->nombre,
                    'audiencia_objetivo' => $product->audiencia_objetivo,
                    'descripcion' => $product->descripcion,
                    'has_consolidated_data' => $product->hasConsolidatedData(),
                    'is_stale' => $product->isConsolidationStale(),
                    'total_buyer_personas' => $product->total_buyer_personas ?? 0,
                    'ultima_consolidacion' => $product->ultima_consolidacion?->diffForHumans(),
                    'top_5_buyer_personas' => $product->top_5_buyer_personas ?? [],
                ];
            });

        // Obtener tipos de copy disponibles
        $copyTypes = CopyGeneration::getCopyTypes();

        // Obtener últimos copies generados
        $recentCopies = CopyGeneration::with('buyerPersona')
            ->latest()
            ->get()
            ->map(function ($copy) {
                return [
                    'id' => $copy->id,
                    'name' => $copy->name,
                    'copy_type' => $copy->copy_type,
                    'copy_type_name' => CopyGeneration::getAllCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
                    'headline' => $copy->headline,
                    'character_count' => $copy->character_count,
                    'created_at' => $copy->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('CopyGenerator/Index', [
            'products' => $products,
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
            'product_id' => 'required|exists:products,id',
            'copy_type' => 'required|string',
            'custom_name' => 'nullable|string|max:255',
            'facebook_ad_objective' => 'nullable|string',
            'facebook_ad_tone' => 'nullable|string',
            'facebook_ad_angle' => 'nullable|string',
            'selected_buyer_persona_index' => 'nullable|integer|min:0|max:4',
            'variations_count' => 'nullable|integer|min:1|max:3',
        ]);

        try {
            // Obtener el producto con sus datos consolidados
            $product = \App\Models\Product::findOrFail($request->product_id);

            // Verificar que el producto tenga datos consolidados
            if (! $product->hasConsolidatedData()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto no tiene datos consolidados. Por favor, consolida los datos primero desde la sección de Productos.',
                ], 422);
            }

            // Preparar opciones adicionales
            $options = [
                'selected_buyer_persona_index' => $request->input('selected_buyer_persona_index'),
                'variations_count' => $request->input('variations_count', 1),
            ];

            if ($request->copy_type === 'facebook_ad') {
                $options['facebook_ad_objective'] = $request->facebook_ad_objective;
                $options['facebook_ad_tone'] = $request->facebook_ad_tone;
                $options['facebook_ad_angle'] = $request->facebook_ad_angle;
            }

            // Generar el copy usando datos consolidados del producto
            $copy = $this->copyService->generateCopyFromProduct(
                $product,
                $request->copy_type,
                $request->custom_name,
                $options
            );

            return response()->json([
                'success' => true,
                'copy' => [
                    'id' => $copy->id,
                    'name' => $copy->name,
                    'copy_type' => $copy->copy_type,
                    'copy_type_name' => CopyGeneration::getAllCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
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
                'copy_type_name' => CopyGeneration::getAllCopyTypes()[$copy->copy_type] ?? $copy->copy_type,
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
