<?php

namespace App\Services;

use App\Models\Product;

class ProductConsolidationService
{
    /**
     * Consolida toda la información de buyer personas de un producto
     */
    public function consolidate(Product $product): Product
    {
        // Obtener todos los buyer personas del producto
        $youtubePersonas = $this->getYoutubeBuyerPersonas($product);
        $googleFormPersonas = $this->getGoogleFormBuyerPersonas($product);

        $allPersonas = collect($youtubePersonas)->merge($googleFormPersonas);

        if ($allPersonas->isEmpty()) {
            throw new \Exception('No hay buyer personas para consolidar en este producto.');
        }

        // Consolidar datos
        $product->update([
            'top_5_buyer_personas' => $this->selectTop5Personas($allPersonas),
            'pain_points_consolidados' => $this->consolidatePainPoints($allPersonas),
            'motivaciones_consolidadas' => $this->consolidateMotivaciones($allPersonas),
            'suenos_consolidados' => $this->consolidateSuenos($allPersonas),
            'objeciones_consolidadas' => $this->consolidateObjeciones($allPersonas),
            'keywords_consolidadas' => $this->consolidateKeywords($allPersonas),
            'canales_preferidos' => $this->consolidateCanales($allPersonas),
            'demografia_promedio' => $this->calculateDemografia($allPersonas),
            'insights_youtube' => $this->generateYoutubeInsights($youtubePersonas),
            'insights_google_forms' => $this->generateGoogleFormsInsights($googleFormPersonas),
            'total_buyer_personas' => $allPersonas->count(),
            'total_youtube_personas' => count($youtubePersonas),
            'total_google_form_personas' => count($googleFormPersonas),
            'ultima_consolidacion' => now(),
        ]);

        return $product->fresh();
    }

    /**
     * Obtener buyer personas de YouTube
     */
    private function getYoutubeBuyerPersonas(Product $product): array
    {
        $personas = [];
        $videos = $product->youtubeVideos()->with('buyerPersonas')->get();

        foreach ($videos as $video) {
            foreach ($video->buyerPersonas as $persona) {
                $personas[] = $this->normalizePersona($persona, 'youtube', $video->title);
            }
        }

        return $personas;
    }

    /**
     * Obtener buyer personas de Google Forms
     */
    private function getGoogleFormBuyerPersonas(Product $product): array
    {
        $personas = [];
        $surveys = $product->formSurveys()->with('buyerPersonas')->get();

        foreach ($surveys as $survey) {
            foreach ($survey->buyerPersonas as $persona) {
                $personas[] = $this->normalizePersona($persona, 'google_forms', $survey->title);
            }
        }

        return $personas;
    }

    /**
     * Normaliza un buyer persona a un formato estándar
     */
    private function normalizePersona($persona, string $source, string $sourceName): array
    {
        return [
            'id' => $persona->id,
            'source' => $source,
            'source_name' => $sourceName,
            'nombre' => $persona->nombre ?? 'Sin nombre',
            'edad' => $persona->edad ?? 'No especificado',
            'ocupacion' => $persona->ocupacion ?? 'No especificado',
            'descripcion' => $persona->descripcion ?? '',
            'motivaciones' => $this->parseJsonField($persona->motivaciones ?? []),
            'pain_points' => $this->parseJsonField($persona->pain_points ?? []),
            'suenos' => $this->parseJsonField($persona->suenos ?? []),
            'objeciones' => $this->parseJsonField($persona->objeciones ?? []),
            'keywords' => $this->parseJsonField($persona->keywords_clave ?? []),
            'canales' => $this->parseJsonField($persona->canales_preferidos ?? []),
            'created_at' => $persona->created_at,
            'completitud' => $this->calculateCompletion($persona),
        ];
    }

    /**
     * Calcula el porcentaje de completitud de un buyer persona
     */
    private function calculateCompletion($persona): int
    {
        $fields = [
            'nombre',
            'edad',
            'ocupacion',
            'descripcion',
            'motivaciones',
            'pain_points',
            'suenos',
            'objeciones',
            'keywords_clave',
            'canales_preferidos',
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (! empty($persona->$field)) {
                $completed++;
            }
        }

        return (int) (($completed / count($fields)) * 100);
    }

    /**
     * Selecciona los top 5 buyer personas basado en completitud y recencia
     */
    private function selectTop5Personas($personas): array
    {
        return $personas
            ->sortByDesc(function ($persona) {
                // Puntaje: 70% completitud + 30% recencia (días desde creación)
                $completitudScore = $persona['completitud'] * 0.7;
                $daysOld = now()->diffInDays($persona['created_at']);
                $recenciaScore = max(0, 30 - ($daysOld * 0.5)); // Máximo 30 puntos, pierde 0.5 por día

                return $completitudScore + $recenciaScore;
            })
            ->take(5)
            ->map(function ($persona) {
                // Remover campos internos
                unset($persona['completitud']);

                return $persona;
            })
            ->values()
            ->toArray();
    }

    /**
     * Consolida pain points con frecuencia
     */
    private function consolidatePainPoints($personas): array
    {
        return $this->consolidateArrayField($personas, 'pain_points');
    }

    /**
     * Consolida motivaciones con frecuencia
     */
    private function consolidateMotivaciones($personas): array
    {
        return $this->consolidateArrayField($personas, 'motivaciones');
    }

    /**
     * Consolida sueños con frecuencia
     */
    private function consolidateSuenos($personas): array
    {
        return $this->consolidateArrayField($personas, 'suenos');
    }

    /**
     * Consolida objeciones con frecuencia
     */
    private function consolidateObjeciones($personas): array
    {
        return $this->consolidateArrayField($personas, 'objeciones');
    }

    /**
     * Consolida keywords con frecuencia
     */
    private function consolidateKeywords($personas): array
    {
        return $this->consolidateArrayField($personas, 'keywords', 20);
    }

    /**
     * Consolida canales preferidos
     */
    private function consolidateCanales($personas): array
    {
        return $this->consolidateArrayField($personas, 'canales', 10);
    }

    /**
     * Método genérico para consolidar arrays con frecuencia
     */
    private function consolidateArrayField($personas, string $field, int $limit = 15): array
    {
        $frequency = [];

        foreach ($personas as $persona) {
            foreach ($persona[$field] as $item) {
                $item = trim($item);
                if (empty($item)) {
                    continue;
                }

                if (! isset($frequency[$item])) {
                    $frequency[$item] = 0;
                }
                $frequency[$item]++;
            }
        }

        // Ordenar por frecuencia y tomar los top N
        arsort($frequency);

        return array_map(function ($item, $count) {
            return [
                'texto' => $item,
                'frecuencia' => $count,
            ];
        }, array_keys(array_slice($frequency, 0, $limit, true)), array_slice($frequency, 0, $limit, true));
    }

    /**
     * Calcula demografía promedio
     */
    private function calculateDemografia($personas): array
    {
        $edades = [];
        $ocupaciones = [];

        foreach ($personas as $persona) {
            // Intentar extraer edad numérica del campo edad (puede contener texto como "25-35 años")
            if ($persona['edad'] !== 'No especificado') {
                // Extraer números del string
                preg_match('/\d+/', $persona['edad'], $matches);
                if (! empty($matches)) {
                    $edades[] = (int) $matches[0];
                }
            }

            if ($persona['ocupacion'] !== 'No especificado') {
                $ocupaciones[] = $persona['ocupacion'];
            }
        }

        return [
            'edad_promedio' => ! empty($edades) ? (int) (array_sum($edades) / count($edades)) : null,
            'edad_rango' => ! empty($edades) ? min($edades).' - '.max($edades) : null,
            'ocupaciones_principales' => $this->getTopN($ocupaciones, 5),
            'total_personas_analizadas' => count($personas),
        ];
    }

    /**
     * Obtiene el elemento más frecuente de un array
     */
    private function getMostFrequent(array $items): ?string
    {
        if (empty($items)) {
            return null;
        }

        $frequency = array_count_values($items);
        arsort($frequency);

        return array_key_first($frequency);
    }

    /**
     * Obtiene los top N elementos más frecuentes
     */
    private function getTopN(array $items, int $n): array
    {
        if (empty($items)) {
            return [];
        }

        $frequency = array_count_values($items);
        arsort($frequency);

        return array_slice(array_keys($frequency), 0, $n);
    }

    /**
     * Genera insights de YouTube
     */
    private function generateYoutubeInsights(array $personas): ?string
    {
        if (empty($personas)) {
            return null;
        }

        $count = count($personas);
        $videos = array_unique(array_column($personas, 'source_name'));

        return "Analizados {$count} buyer personas desde ".count($videos).' videos de YouTube. '.
            'Los patrones muestran audiencia activa en contenido de video con alto engagement.';
    }

    /**
     * Genera insights de Google Forms
     */
    private function generateGoogleFormsInsights(array $personas): ?string
    {
        if (empty($personas)) {
            return null;
        }

        $count = count($personas);
        $forms = array_unique(array_column($personas, 'source_name'));

        return "Analizados {$count} buyer personas desde ".count($forms).' formularios de Google Forms. '.
            'Datos directos de encuestas con respuestas estructuradas.';
    }

    /**
     * Helper para parsear campos JSON
     */
    private function parseJsonField($field): array
    {
        if (is_array($field)) {
            return $field;
        }

        if (is_string($field) && ! empty($field)) {
            $decoded = json_decode($field, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
