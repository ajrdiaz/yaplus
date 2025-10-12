<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductConsolidationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    protected $consolidationService;

    public function __construct(ProductConsolidationService $consolidationService)
    {
        $this->consolidationService = $consolidationService;
    }

    /**
     * Mostrar listado de productos
     */
    public function index()
    {
        $products = Product::latest()->get();

        return Inertia::render('Products/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Crear nuevo producto
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'audiencia_objetivo' => 'nullable|string',
            'descripcion' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'product' => $product,
        ]);
    }

    /**
     * Actualizar producto
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'audiencia_objetivo' => 'nullable|string',
            'descripcion' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente',
            'product' => $product,
        ]);
    }

    /**
     * Eliminar producto
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente',
        ]);
    }

    /**
     * Consolidar datos de buyer personas de un producto
     */
    public function consolidate(Product $product)
    {
        try {
            $consolidatedProduct = $this->consolidationService->consolidate($product);

            return response()->json([
                'success' => true,
                'message' => 'Datos consolidados exitosamente',
                'product' => $consolidatedProduct,
                'stats' => [
                    'total_personas' => $consolidatedProduct->total_buyer_personas,
                    'youtube_personas' => $consolidatedProduct->total_youtube_personas,
                    'google_form_personas' => $consolidatedProduct->total_google_form_personas,
                    'top_5_selected' => count($consolidatedProduct->top_5_buyer_personas ?? []),
                    'pain_points' => count($consolidatedProduct->pain_points_consolidados ?? []),
                    'ultima_consolidacion' => $consolidatedProduct->ultima_consolidacion?->diffForHumans(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consolidar datos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalles de consolidación de un producto
     */
    public function showConsolidation(Product $product)
    {
        $product->load(['youtubeVideos', 'formSurveys']);

        return Inertia::render('Products/Consolidation', [
            'product' => [
                'id' => $product->id,
                'nombre' => $product->nombre,
                'descripcion' => $product->descripcion,
                'audiencia_objetivo' => $product->audiencia_objetivo,
                'top_5_buyer_personas' => $product->top_5_buyer_personas,
                'pain_points_consolidados' => $product->pain_points_consolidados,
                'motivaciones_consolidadas' => $product->motivaciones_consolidadas,
                'suenos_consolidados' => $product->suenos_consolidados,
                'objeciones_consolidadas' => $product->objeciones_consolidadas,
                'keywords_consolidadas' => $product->keywords_consolidadas,
                'canales_preferidos' => $product->canales_preferidos,
                'demografia_promedio' => $product->demografia_promedio,
                'insights_youtube' => $product->insights_youtube,
                'insights_google_forms' => $product->insights_google_forms,
                'total_buyer_personas' => $product->total_buyer_personas,
                'total_youtube_personas' => $product->total_youtube_personas,
                'total_google_form_personas' => $product->total_google_form_personas,
                'ultima_consolidacion' => $product->ultima_consolidacion?->format('Y-m-d H:i:s'),
                'ultima_consolidacion_humano' => $product->ultima_consolidacion?->diffForHumans(),
            ],
            'hasConsolidatedData' => $product->hasConsolidatedData(),
            'isStale' => $product->isConsolidationStale(),
        ]);
    }
}
