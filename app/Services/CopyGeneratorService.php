<?php

namespace App\Services;

use App\Models\CopyGeneration;
use GuzzleHttp\Client as GuzzleClient;

class CopyGeneratorService
{
    /**
     * Genera copy basado en un buyer persona
     */
    public function generateCopy($buyerPersona, string $copyType, ?string $customName = null, ?int $productId = null, array $options = []): CopyGeneration
    {
        $buyerData = $this->prepareBuyerPersonaData($buyerPersona);
        $prompt = $this->buildPrompt($buyerData, $copyType, $productId, $options);

        // Usar Guzzle directamente para llamar a la API de OpenAI
        $guzzle = new GuzzleClient;

        $apiResponse = $guzzle->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.config('openai.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto copywriter especializado en marketing digital y publicidad persuasiva. Creas textos que conectan emocionalmente con la audiencia y generan conversiones.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
            ],
        ]);

        $response = json_decode($apiResponse->getBody()->getContents(), true);

        $generatedContent = $response['choices'][0]['message']['content'];
        $parsedContent = $this->parseGeneratedContent($generatedContent, $copyType);

        // Guardar en base de datos
        $copy = CopyGeneration::create([
            'buyer_persona_id' => $buyerPersona->id,
            'buyer_persona_type' => get_class($buyerPersona),
            'product_id' => $productId,
            'copy_type' => $copyType,
            'name' => $customName ?? $this->generateDefaultName($buyerPersona, $copyType),
            'headline' => $parsedContent['headline'] ?? null,
            'subheadline' => $parsedContent['subheadline'] ?? null,
            'body' => $parsedContent['body'] ?? null,
            'cta' => $parsedContent['cta'] ?? null,
            'additional_data' => $parsedContent['additional_data'] ?? null,
            'character_count' => strlen($parsedContent['body'] ?? ''),
        ]);

        return $copy;
    }

    /**
     * Genera copy basado en datos consolidados de un producto
     */
    public function generateCopyFromProduct($product, string $copyType, ?string $customName = null, array $options = []): CopyGeneration
    {
        $selectedBuyerIndex = $options['selected_buyer_persona_index'] ?? null;
        $productData = $this->prepareProductData($product, $selectedBuyerIndex);
        $prompt = $this->buildPromptFromProduct($productData, $copyType, $options);

        // Usar Guzzle directamente para llamar a la API de OpenAI
        $guzzle = new GuzzleClient;

        $apiResponse = $guzzle->post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.config('openai.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un experto copywriter especializado en marketing digital y publicidad persuasiva. Creas textos que conectan emocionalmente con la audiencia y generan conversiones.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.8,
            ],
        ]);

        $response = json_decode($apiResponse->getBody()->getContents(), true);

        $generatedContent = $response['choices'][0]['message']['content'];
        $variationsCount = $options['variations_count'] ?? 1;
        $parsedContent = $this->parseGeneratedContent($generatedContent, $copyType, $variationsCount);

        // Guardar en base de datos
        $copy = CopyGeneration::create([
            'buyer_persona_id' => null, // No asociado a un buyer persona específico
            'buyer_persona_type' => null,
            'product_id' => $product->id,
            'copy_type' => $copyType,
            'variations_count' => $variationsCount,
            'name' => $customName ?? $this->generateDefaultNameFromProduct($product, $copyType),
            'headline' => $parsedContent['headline'] ?? null,
            'subheadline' => $parsedContent['subheadline'] ?? null,
            'body' => $parsedContent['body'] ?? null,
            'cta' => $parsedContent['cta'] ?? null,
            'additional_data' => $parsedContent['additional_data'] ?? null,
            'character_count' => strlen($parsedContent['body'] ?? ''),
        ]);

        return $copy;
    }

    /**
     * Prepara los datos consolidados del producto para el prompt
     */
    private function prepareProductData($product, ?int $selectedBuyerIndex = null): array
    {
        // Extraer los top 5 buyer personas consolidados
        $top5Personas = $product->top_5_buyer_personas ?? [];

        // Consolidar datos demográficos
        $demografia = $product->demografia_promedio ?? [];

        return [
            'nombre' => $product->nombre,
            'descripcion' => $product->descripcion ?? '',
            'audiencia_objetivo' => $product->audiencia_objetivo ?? '',
            'total_personas' => $product->total_buyer_personas ?? 0,
            'edad_promedio' => $demografia['edad_promedio'] ?? 'No especificado',
            'edad_rango' => $demografia['edad_rango'] ?? 'No especificado',
            'ocupaciones_principales' => $demografia['ocupaciones_principales'] ?? [],
            'pain_points' => $product->pain_points_consolidados ?? [],
            'motivaciones' => $product->motivaciones_consolidadas ?? [],
            'suenos' => $product->suenos_consolidados ?? [],
            'objeciones' => $product->objeciones_consolidadas ?? [],
            'keywords' => $product->keywords_consolidadas ?? [],
            'canales_preferidos' => $product->canales_preferidos ?? [],
            'top_5_personas' => $top5Personas,
            'selected_buyer_index' => $selectedBuyerIndex,
            'insights_youtube' => $product->insights_youtube,
            'insights_google_forms' => $product->insights_google_forms,
        ];
    }

    /**
     * Prepara los datos del buyer persona para el prompt
     * Compatible con BuyerPersona (Google Forms) y YoutubeBuyerPersona
     */
    private function prepareBuyerPersonaData($buyerPersona): array
    {
        return [
            // Nombre - puede ser 'nombre' o 'nombre_persona'
            'nombre' => $buyerPersona->nombre_persona ?? $buyerPersona->nombre ?? 'Cliente Ideal',
            // Edad - puede ser 'edad' o 'edad_rango'
            'edad' => $buyerPersona->edad_rango ?? $buyerPersona->edad ?? 'No especificado',
            // Género
            'genero' => $buyerPersona->genero ?? 'No especificado',
            // Ubicación
            'ubicacion' => $buyerPersona->ubicacion_geografica ?? 'No especificado',
            // Ocupación
            'ocupacion' => $buyerPersona->ocupacion ?? 'No especificado',
            // Nivel educativo - puede estar en descripcion o como campo separado
            'nivel_educativo' => $buyerPersona->nivel_educativo ?? 'No especificado',
            // Arrays JSON - compatibles con ambos modelos
            'motivaciones' => $this->parseJsonField($buyerPersona->motivaciones ?? []),
            'pain_points' => $this->parseJsonField($buyerPersona->pain_points ?? []),
            'suenos' => $this->parseJsonField($buyerPersona->suenos ?? []),
            'objeciones' => $this->parseJsonField($buyerPersona->objeciones ?? []),
            'canales_preferidos' => $this->parseJsonField($buyerPersona->canales_preferidos ?? []),
            'keywords' => $this->parseJsonField($buyerPersona->keywords_clave ?? []),
        ];
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

    /**
     * Construye el prompt según el tipo de copy
     */
    private function buildPrompt(array $buyerData, string $copyType, ?int $productId = null, array $options = []): string
    {
        $baseContext = $this->buildBaseContext($buyerData, $productId);

        // Construir prompt de Facebook Ads dinámicamente con opciones adicionales
        $facebookAdPrompt = "{$baseContext}\n\n";

        if (! empty($options['facebook_ad_objective'])) {
            $objectives = [
                'traffic' => 'generar tráfico al sitio web',
                'conversions' => 'generar conversiones y ventas',
                'leads' => 'generar leads y registros',
                'awareness' => 'aumentar el reconocimiento de marca',
                'engagement' => 'generar interacción y engagement',
            ];
            $objectiveText = $objectives[$options['facebook_ad_objective']] ?? $options['facebook_ad_objective'];
            $facebookAdPrompt .= "OBJETIVO DEL ANUNCIO: {$objectiveText}\n";
        }

        if (! empty($options['facebook_ad_tone'])) {
            $tones = [
                'professional' => 'profesional y confiable',
                'casual' => 'casual, amigable y cercano',
                'urgent' => 'urgente, directo y con sentido de inmediatez',
                'inspirational' => 'inspiracional y motivador',
                'educational' => 'educativo e informativo',
                'emotional' => 'emocional y conectando con sentimientos',
            ];
            $toneText = $tones[$options['facebook_ad_tone']] ?? $options['facebook_ad_tone'];
            $facebookAdPrompt .= "TONO DE COMUNICACIÓN: {$toneText}\n";
        }

        if (! empty($options['facebook_ad_angle'])) {
            $facebookAdPrompt .= "ÁNGULO DE VENTA PRINCIPAL: {$options['facebook_ad_angle']}\n";
        }

        $facebookAdPrompt .= '
Crea un anuncio para Facebook/Instagram Ads con los siguientes elementos:

1. TEXTO PRINCIPAL (VERSIÓN CORTA): Máximo 125 caracteres. Debe ser directo, captar atención inmediata y mencionar el beneficio clave usando el ángulo de venta especificado.

2. TEXTO PRINCIPAL (VERSIÓN LARGA): Entre 400 y 700 caracteres. Expande el mensaje, cuenta una historia breve, menciona pain points, beneficios detallados, maneja objeciones y conecta emocionalmente. Usa el tono especificado y las keywords que ellos usan. IMPORTANTE: Usa saltos de línea cada 2-3 oraciones para mejorar la legibilidad (presiona Enter entre párrafos cortos).

3. TITULAR (VERSIÓN CORTA): Máximo 27 caracteres. Frase ultra-corta y poderosa que capte atención. Debe ser impactante.

4. TITULAR (VERSIÓN LARGA): Máximo 60 caracteres. Versión expandida del titular que incluya más contexto o beneficio usando el ángulo de venta.

5. DESCRIPCIÓN DEL TITULAR: Máximo 60 caracteres. Complemento que expande el titular con información adicional o beneficio secundario.

Asegúrate de trabajar el ángulo de venta especificado de manera prominente en todos los elementos.

CRITERIOS DE CALIDAD QUE DEBEN CUMPLIR TODOS LOS COPYS:
- Originales y sin rastro de IA. Nada de frases genéricas ni plantillas evidentes.
- Conversacionales y naturales, como si fueran escritos por un experto humano que conoce el mercado.
- Estructurados para facilitar la lectura, sin párrafos de más de 150 caracteres.
- Altamente persuasivos, utilizando técnicas de copywriting direct-response: storytelling, prueba social, ruptura de patrón, beneficios tangibles/intangibles, etc.

Formato de respuesta EXACTO:
TEXTO_CORTO: [texto]
TEXTO_LARGO: [texto]
TITULAR_CORTO: [texto]
TITULAR_LARGO: [texto]
DESCRIPCION: [texto]
';

        $prompts = [
            'facebook_ad' => $facebookAdPrompt,

            'google_ad' => "
{$baseContext}

Crea un anuncio para Google Ads que:
1. HEADLINE 1 (máximo 30 caracteres): Problema/pain point principal
2. HEADLINE 2 (máximo 30 caracteres): Solución/beneficio
3. HEADLINE 3 (máximo 30 caracteres): Resultado deseado
4. DESCRIPTION 1 (máximo 90 caracteres): Expandir el beneficio y superar objeción principal
5. DESCRIPTION 2 (máximo 90 caracteres): Call to action con sentido de urgencia

Formato de respuesta:
HEADLINE1: [texto]
HEADLINE2: [texto]
HEADLINE3: [texto]
DESC1: [texto]
DESC2: [texto]
",

            'landing_hero' => "
{$baseContext}

Crea el Hero Section de una landing page que:
1. TITULAR PRINCIPAL (H1): Una frase poderosa que conecte con el mayor pain point (máximo 60 caracteres)
2. SUBTÍTULO (H2): Promesa de transformación que conecte con su mayor sueño (máximo 120 caracteres)
3. BULLET POINTS: 3 beneficios clave que resuelvan sus pain points principales
4. CTA PRINCIPAL: Botón de acción claro y persuasivo (máximo 25 caracteres)
5. CTA SECUNDARIO (opcional): Alternativa de menor compromiso (máximo 25 caracteres)

Formato de respuesta:
H1: [texto]
H2: [texto]
BENEFIT1: [texto]
BENEFIT2: [texto]
BENEFIT3: [texto]
CTA_PRIMARY: [texto]
CTA_SECONDARY: [texto]
",

            'email_subject' => "
{$baseContext}

Crea 5 asuntos de email diferentes que:
- Sean intrigantes y generen curiosidad
- Usen sus pain points o sueños
- Sean personalizados y directos
- Máximo 50 caracteres cada uno
- Incluyan emojis relevantes cuando sea apropiado

Formato de respuesta:
SUBJECT1: [texto]
SUBJECT2: [texto]
SUBJECT3: [texto]
SUBJECT4: [texto]
SUBJECT5: [texto]
",

            'email_body' => "
{$baseContext}

Crea el cuerpo de un email de bienvenida/venta que:
1. APERTURA: Saludo personalizado y conexión inmediata (2 líneas)
2. PAIN POINT: Mencionar su problema principal con empatía (2-3 líneas)
3. SOLUCIÓN: Presentar la solución y beneficio principal (3-4 líneas)
4. PRUEBA SOCIAL: Breve testimonio o estadística (1-2 líneas)
5. CTA: Llamado a la acción claro (1-2 líneas)
6. CIERRE: Despedida cálida y firma

Tono: Conversacional, amigable, como si hablaras con un amigo.

Formato de respuesta:
OPENING: [texto]
PAIN: [texto]
SOLUTION: [texto]
SOCIAL_PROOF: [texto]
CTA: [texto]
CLOSING: [texto]
",

            'instagram_post' => "
{$baseContext}

Crea un post para Instagram que:
1. HOOK: Primera línea que pare el scroll (impactante, pregunta o estadística)
2. BODY: 8-12 líneas que cuenten una historia, compartan un tip o insight valioso conectado a sus intereses
3. CTA: Llamado a interacción (comentar, guardar, compartir, ir a bio)
4. HASHTAGS: 15-20 hashtags relevantes mezclando populares y de nicho

Usa emojis estratégicamente, saltos de línea para legibilidad.

Formato de respuesta:
HOOK: [texto]
BODY: [texto]
CTA: [texto]
HASHTAGS: [texto]
",

            'linkedin_post' => "
{$baseContext}

Crea un post profesional para LinkedIn que:
1. HOOK: Primera línea potente (pregunta, dato, afirmación controversial)
2. HISTORIA/INSIGHT: 6-10 líneas compartiendo una lección, experiencia o dato valioso profesional
3. LECCIONES: 3-5 puntos clave aprendidos (formato lista)
4. CIERRE: Pregunta que invite a comentar y debatir

Tono: Profesional pero humano, storytelling, insights valiosos.

Formato de respuesta:
HOOK: [texto]
STORY: [texto]
LESSON1: [texto]
LESSON2: [texto]
LESSON3: [texto]
CLOSING: [texto]
",

            'twitter_thread' => "
{$baseContext}

Crea un hilo de Twitter/X (8-10 tweets) que:
- Tweet 1: Hook potente que enganche
- Tweets 2-8: Desarrollo del tema con tips, datos, historias cortas
- Tweet final: CTA para engagement (RT, comentar, seguir)
- Máximo 280 caracteres por tweet
- Usa emojis y formato claro

Formato de respuesta:
TWEET1: [texto]
TWEET2: [texto]
TWEET3: [texto]
TWEET4: [texto]
TWEET5: [texto]
TWEET6: [texto]
TWEET7: [texto]
TWEET8: [texto]
",
        ];

        return $prompts[$copyType] ?? $prompts['facebook_ad'];
    }

    /**
     * Construye el contexto base del buyer persona
     */
    private function buildBaseContext(array $buyerData, ?int $productId = null): string
    {
        $motivaciones = ! empty($buyerData['motivaciones']) ? implode(', ', array_slice($buyerData['motivaciones'], 0, 5)) : 'No especificadas';
        $painPoints = ! empty($buyerData['pain_points']) ? implode(', ', array_slice($buyerData['pain_points'], 0, 5)) : 'No especificados';
        $suenos = ! empty($buyerData['suenos']) ? implode(', ', array_slice($buyerData['suenos'], 0, 5)) : 'No especificados';
        $objeciones = ! empty($buyerData['objeciones']) ? implode(', ', array_slice($buyerData['objeciones'], 0, 3)) : 'No especificadas';
        $keywords = ! empty($buyerData['keywords']) ? implode(', ', array_slice($buyerData['keywords'], 0, 10)) : 'No especificadas';

        $context = "
BUYER PERSONA:
- Nombre: {$buyerData['nombre']}
- Edad: {$buyerData['edad']}
- Género: {$buyerData['genero']}
- Ubicación: {$buyerData['ubicacion']}
- Ocupación: {$buyerData['ocupacion']}
- Nivel Educativo: {$buyerData['nivel_educativo']}

MOTIVACIONES PRINCIPALES:
{$motivaciones}

PAIN POINTS (Problemas/Frustraciones):
{$painPoints}

SUEÑOS/ASPIRACIONES:
{$suenos}

OBJECIONES COMUNES:
{$objeciones}

KEYWORDS QUE USAN:
{$keywords}
";

        // Agregar información del producto si está disponible
        if ($productId) {
            $product = \App\Models\Product::find($productId);
            if ($product) {
                $context .= "\n\nINFORMACIÓN DEL PRODUCTO:";
                $context .= "\n- Nombre: {$product->nombre}";
                if ($product->descripcion) {
                    $context .= "\n- Descripción: {$product->descripcion}";
                }
                if ($product->audiencia_objetivo) {
                    $context .= "\n- Audiencia Objetivo: {$product->audiencia_objetivo}";
                }
                if ($product->puntos_dolor) {
                    $context .= "\n- Puntos de Dolor que Resuelve: {$product->puntos_dolor}";
                }
                if ($product->beneficios_clave) {
                    $context .= "\n- Beneficios Clave: {$product->beneficios_clave}";
                }
                if ($product->propuesta_valor) {
                    $context .= "\n- Propuesta de Valor: {$product->propuesta_valor}";
                }
            }
        }

        return $context;
    }

    /**
     * Parsea el contenido generado según el tipo
     */
    private function parseGeneratedContent(string $content, string $copyType, int $variationsCount = 1): array
    {
        $parsed = [];

        switch ($copyType) {
            case 'facebook_ad':
                // Si hay múltiples variaciones, parsear cada una
                if ($variationsCount > 1) {
                    $variations = [];

                    for ($i = 1; $i <= $variationsCount; $i++) {
                        // Buscar cada variación con su número (más flexible: acepta VARIACION, Variacion, Variación con/sin guión bajo o espacio)
                        $pattern = '/VARIACI[OÓ]N[\s_]'.$i.'[\s:]*\n*(.+?)(?=VARIACI[OÓ]N[\s_]\d+|$)/is';
                        preg_match($pattern, $content, $variationContent);

                        if (! empty($variationContent[1])) {
                            $varContent = $variationContent[1];

                            preg_match('/TEXTO_CORTO:\s*(.+?)(?:\n|$)/is', $varContent, $textoCorto);
                            preg_match('/TEXTO_LARGO:\s*(.+?)(?:\n(?:TITULAR_CORTO:|$))/is', $varContent, $textoLargo);
                            preg_match('/TITULAR_CORTO:\s*(.+?)(?:\n|$)/i', $varContent, $titularCorto);
                            preg_match('/TITULAR_LARGO:\s*(.+?)(?:\n|$)/i', $varContent, $titularLargo);
                            preg_match('/DESCRIPCION:\s*(.+?)(?:\n|$)/is', $varContent, $descripcion);

                            $variations[] = [
                                'texto_corto' => trim($textoCorto[1] ?? ''),
                                'texto_largo' => trim($textoLargo[1] ?? ''),
                                'titular_corto' => trim($titularCorto[1] ?? ''),
                                'titular_largo' => trim($titularLargo[1] ?? ''),
                                'descripcion' => trim($descripcion[1] ?? ''),
                            ];
                        }
                    }

                    // La primera variación se usa para los campos principales
                    if (! empty($variations[0])) {
                        $parsed['headline'] = $variations[0]['titular_corto'];
                        $parsed['subheadline'] = $variations[0]['titular_largo'];
                        $parsed['body'] = $variations[0]['texto_largo'];
                        $parsed['cta'] = $variations[0]['descripcion'];
                    }

                    $parsed['additional_data'] = [
                        'variations' => $variations,
                    ];
                } else {
                    // Parseo normal para una sola variación
                    preg_match('/TEXTO_CORTO:\s*(.+?)(?:\n|$)/is', $content, $textoCorto);
                    preg_match('/TEXTO_LARGO:\s*(.+?)(?:\n(?:TITULAR_CORTO:|$))/is', $content, $textoLargo);
                    preg_match('/TITULAR_CORTO:\s*(.+?)(?:\n|$)/i', $content, $titularCorto);
                    preg_match('/TITULAR_LARGO:\s*(.+?)(?:\n|$)/i', $content, $titularLargo);
                    preg_match('/DESCRIPCION:\s*(.+?)(?:\n|$)/is', $content, $descripcion);

                    $parsed['headline'] = trim($titularCorto[1] ?? '');
                    $parsed['subheadline'] = trim($titularLargo[1] ?? '');
                    $parsed['body'] = trim($textoLargo[1] ?? '');
                    $parsed['cta'] = trim($descripcion[1] ?? '');
                    $parsed['additional_data'] = [
                        'texto_corto' => trim($textoCorto[1] ?? ''),
                        'texto_largo' => trim($textoLargo[1] ?? ''),
                        'titular_corto' => trim($titularCorto[1] ?? ''),
                        'titular_largo' => trim($titularLargo[1] ?? ''),
                        'descripcion' => trim($descripcion[1] ?? ''),
                    ];
                }
                break;

            case 'google_ad':
                preg_match_all('/HEADLINE\d:\s*(.+?)(?:\n|$)/i', $content, $headlines);
                preg_match_all('/DESC\d:\s*(.+?)(?:\n|$)/i', $content, $descriptions);

                $parsed['headline'] = implode(' | ', array_map('trim', $headlines[1] ?? []));
                $parsed['body'] = implode(' | ', array_map('trim', $descriptions[1] ?? []));
                $parsed['additional_data'] = [
                    'headlines' => array_map('trim', $headlines[1] ?? []),
                    'descriptions' => array_map('trim', $descriptions[1] ?? []),
                ];
                break;

            case 'landing_hero':
                // Si hay múltiples variaciones, parsear cada una
                if ($variationsCount > 1) {
                    $variations = [];

                    for ($i = 1; $i <= $variationsCount; $i++) {
                        // Buscar cada variación con su número (más flexible: acepta VARIACION, Variacion, Variación con/sin guión bajo o espacio)
                        $pattern = '/VARIACI[OÓ]N[\s_]'.$i.'[\s:]*\n*(.+?)(?=VARIACI[OÓ]N[\s_]\d+|$)/is';
                        preg_match($pattern, $content, $variationContent);

                        if (! empty($variationContent[1])) {
                            $varContent = $variationContent[1];

                            preg_match('/H1:\s*(.+?)(?:\n|$)/i', $varContent, $h1);
                            preg_match('/H2:\s*(.+?)(?:\n|$)/i', $varContent, $h2);
                            preg_match_all('/BENEFIT\d:\s*(.+?)(?:\n|$)/i', $varContent, $benefits);
                            preg_match('/CTA_PRIMARY:\s*(.+?)(?:\n|$)/i', $varContent, $ctaPrimary);
                            preg_match('/CTA_SECONDARY:\s*(.+?)(?:\n|$)/i', $varContent, $ctaSecondary);

                            $variations[] = [
                                'h1' => trim($h1[1] ?? ''),
                                'h2' => trim($h2[1] ?? ''),
                                'benefits' => array_map('trim', $benefits[1] ?? []),
                                'cta_primary' => trim($ctaPrimary[1] ?? ''),
                                'cta_secondary' => trim($ctaSecondary[1] ?? ''),
                            ];
                        }
                    }

                    // La primera variación se usa para los campos principales
                    if (! empty($variations[0])) {
                        $parsed['headline'] = $variations[0]['h1'];
                        $parsed['subheadline'] = $variations[0]['h2'];
                        $parsed['body'] = implode("\n", $variations[0]['benefits']);
                        $parsed['cta'] = $variations[0]['cta_primary'];
                    }

                    $parsed['additional_data'] = [
                        'variations' => $variations,
                    ];
                } else {
                    // Parseo normal para una sola variación
                    preg_match('/H1:\s*(.+?)(?:\n|$)/i', $content, $h1);
                    preg_match('/H2:\s*(.+?)(?:\n|$)/i', $content, $h2);
                    preg_match_all('/BENEFIT\d:\s*(.+?)(?:\n|$)/i', $content, $benefits);
                    preg_match('/CTA_PRIMARY:\s*(.+?)(?:\n|$)/i', $content, $ctaPrimary);
                    preg_match('/CTA_SECONDARY:\s*(.+?)(?:\n|$)/i', $content, $ctaSecondary);

                    $parsed['headline'] = trim($h1[1] ?? '');
                    $parsed['subheadline'] = trim($h2[1] ?? '');
                    $parsed['body'] = implode("\n", array_map('trim', $benefits[1] ?? []));
                    $parsed['cta'] = trim($ctaPrimary[1] ?? '');
                    $parsed['additional_data'] = [
                        'benefits' => array_map('trim', $benefits[1] ?? []),
                        'cta_secondary' => trim($ctaSecondary[1] ?? ''),
                    ];
                }
                break;

            case 'email_subject':
                preg_match_all('/SUBJECT\d:\s*(.+?)(?:\n|$)/i', $content, $subjects);
                $parsed['headline'] = trim($subjects[1][0] ?? '');
                $parsed['additional_data'] = [
                    'all_subjects' => array_map('trim', $subjects[1] ?? []),
                ];
                break;

            case 'email_body':
                preg_match('/OPENING:\s*(.+?)(?:\n(?:[A-Z]+:|$))/is', $content, $opening);
                preg_match('/PAIN:\s*(.+?)(?:\n(?:[A-Z]+:|$))/is', $content, $pain);
                preg_match('/SOLUTION:\s*(.+?)(?:\n(?:[A-Z]+:|$))/is', $content, $solution);
                preg_match('/SOCIAL_PROOF:\s*(.+?)(?:\n(?:[A-Z]+:|$))/is', $content, $proof);
                preg_match('/CTA:\s*(.+?)(?:\n(?:[A-Z]+:|$))/is', $content, $cta);
                preg_match('/CLOSING:\s*(.+?)(?:\n|$)/is', $content, $closing);

                $fullBody = trim(($opening[1] ?? '')."\n\n".($pain[1] ?? '')."\n\n".
                               ($solution[1] ?? '')."\n\n".($proof[1] ?? '')."\n\n".
                               ($cta[1] ?? '')."\n\n".($closing[1] ?? ''));

                $parsed['body'] = $fullBody;
                $parsed['additional_data'] = [
                    'opening' => trim($opening[1] ?? ''),
                    'pain' => trim($pain[1] ?? ''),
                    'solution' => trim($solution[1] ?? ''),
                    'social_proof' => trim($proof[1] ?? ''),
                    'cta' => trim($cta[1] ?? ''),
                    'closing' => trim($closing[1] ?? ''),
                ];
                break;

            case 'instagram_post':
            case 'linkedin_post':
            case 'twitter_thread':
                // Para posts sociales, guardar todo el contenido
                $parsed['body'] = $content;
                break;

            default:
                $parsed['body'] = $content;
        }

        return $parsed;
    }

    /**
     * Construye el prompt basado en datos consolidados del producto
     */
    private function buildPromptFromProduct(array $productData, string $copyType, array $options = []): string
    {
        $baseContext = $this->buildProductContext($productData);
        $variationsCount = $options['variations_count'] ?? 1;

        // Construir prompt de Facebook Ads dinámicamente con opciones adicionales
        $facebookAdPrompt = "{$baseContext}\n\n";

        if (! empty($options['facebook_ad_objective'])) {
            $objectives = [
                'traffic' => 'generar tráfico al sitio web',
                'conversions' => 'generar conversiones y ventas',
                'leads' => 'generar leads y registros',
                'awareness' => 'aumentar el reconocimiento de marca',
                'engagement' => 'generar interacción y engagement',
            ];
            $objectiveText = $objectives[$options['facebook_ad_objective']] ?? $options['facebook_ad_objective'];
            $facebookAdPrompt .= "OBJETIVO DEL ANUNCIO: {$objectiveText}\n";
        }

        if (! empty($options['facebook_ad_tone'])) {
            $tones = [
                'professional' => 'profesional y confiable',
                'casual' => 'casual, amigable y cercano',
                'urgent' => 'urgente, directo y con sentido de inmediatez',
                'inspirational' => 'inspiracional y motivador',
                'educational' => 'educativo e informativo',
                'emotional' => 'emocional y conectando con sentimientos',
            ];
            $toneText = $tones[$options['facebook_ad_tone']] ?? $options['facebook_ad_tone'];
            $facebookAdPrompt .= "TONO DE COMUNICACIÓN: {$toneText}\n";
        }

        if (! empty($options['facebook_ad_angle'])) {
            $facebookAdPrompt .= "ÁNGULO DE VENTA PRINCIPAL: {$options['facebook_ad_angle']}\n";
        }

        // Agregar instrucción para variaciones si se solicita
        if ($variationsCount > 1) {
            $facebookAdPrompt .= "\n⚠️ IMPORTANTE: GENERA {$variationsCount} VARIACIONES COMPLETAS Y DIFERENTES del anuncio.\n";
            $facebookAdPrompt .= "Cada variación debe tener un enfoque, ángulo o tono ligeramente diferente.\n";
            $facebookAdPrompt .= "Numera cada variación como VARIACION_1, VARIACION_2, etc.\n\n";
        }

        $facebookAdPrompt .= '
Crea un anuncio para Facebook/Instagram Ads con los siguientes elementos:

1. TEXTO PRINCIPAL (VERSIÓN CORTA): Máximo 125 caracteres. Debe ser directo, captar atención inmediata y mencionar el beneficio clave usando el ángulo de venta especificado.

2. TEXTO PRINCIPAL (VERSIÓN LARGA): Entre 400 y 700 caracteres. Expande el mensaje, cuenta una historia breve, menciona pain points, beneficios detallados, maneja objeciones y conecta emocionalmente. Usa el tono especificado y las keywords que ellos usan. IMPORTANTE: Usa saltos de línea cada 2-3 oraciones para mejorar la legibilidad (presiona Enter entre párrafos cortos).

3. TITULAR (VERSIÓN CORTA): Máximo 27 caracteres. Frase ultra-corta y poderosa que capte atención. Debe ser impactante.

4. TITULAR (VERSIÓN LARGA): Máximo 60 caracteres. Versión expandida del titular que incluya más contexto o beneficio usando el ángulo de venta.

5. DESCRIPCIÓN DEL TITULAR: Máximo 60 caracteres. Complemento que expande el titular con información adicional o beneficio secundario.

Asegúrate de trabajar el ángulo de venta especificado de manera prominente en todos los elementos.

CRITERIOS DE CALIDAD QUE DEBEN CUMPLIR TODOS LOS COPYS:
- Originales y sin rastro de IA. Nada de frases genéricas ni plantillas evidentes.
- Conversacionales y naturales, como si fueran escritos por un experto humano que conoce el mercado.
- Estructurados para facilitar la lectura, sin párrafos de más de 150 caracteres.
- Altamente persuasivos, utilizando técnicas de copywriting direct-response: storytelling, prueba social, ruptura de patrón, beneficios tangibles/intangibles, etc.

Formato de respuesta EXACTO:
TEXTO_CORTO: [texto]
TEXTO_LARGO: [texto]
TITULAR_CORTO: [texto]
TITULAR_LARGO: [texto]
DESCRIPCION: [texto]
';

        // Landing Hero Prompt con variaciones
        $landingHeroPrompt = "{$baseContext}\n\n";

        // Agregar instrucción para variaciones si se solicita
        if ($variationsCount > 1) {
            $landingHeroPrompt .= "⚠️ IMPORTANTE: GENERA {$variationsCount} VARIACIONES COMPLETAS Y DIFERENTES del Hero Section.\n";
            $landingHeroPrompt .= "Cada variación debe tener un enfoque o ángulo ligeramente diferente.\n";
            $landingHeroPrompt .= "Numera cada variación como VARIACION_1, VARIACION_2, etc.\n\n";
        }

        $landingHeroPrompt .= '
Crea el Hero Section de una landing page que:
1. TITULAR PRINCIPAL (H1): Una frase poderosa que conecte con el mayor pain point (máximo 60 caracteres)
2. SUBTÍTULO (H2): Promesa de transformación que conecte con su mayor sueño (máximo 120 caracteres)
3. BULLET POINTS: 3 beneficios clave que resuelvan sus pain points principales
4. CTA PRINCIPAL: Botón de acción claro y persuasivo (máximo 25 caracteres)
5. CTA SECUNDARIO (opcional): Alternativa de menor compromiso (máximo 25 caracteres)

Formato de respuesta:
H1: [texto]
H2: [texto]
BENEFIT1: [texto]
BENEFIT2: [texto]
BENEFIT3: [texto]
CTA_PRIMARY: [texto]
CTA_SECONDARY: [texto]
';

        $prompts = [
            'facebook_ad' => $facebookAdPrompt,
            'landing_hero' => $landingHeroPrompt,
        ];

        return $prompts[$copyType] ?? $prompts['facebook_ad'];
    }

    /**
     * Construye el contexto base del producto consolidado
     */
    private function buildProductContext(array $productData): string
    {
        $selectedBuyerIndex = $productData['selected_buyer_index'] ?? null;

        // Si se seleccionó un buyer específico
        if ($selectedBuyerIndex !== null && isset($productData['top_5_personas'][$selectedBuyerIndex])) {
            return $this->buildSpecificBuyerContext($productData, $selectedBuyerIndex);
        }

        // Usar todos los datos consolidados
        return $this->buildConsolidatedContext($productData);
    }

    /**
     * Construye el contexto para un buyer persona específico
     */
    private function buildSpecificBuyerContext(array $productData, int $buyerIndex): string
    {
        $persona = $productData['top_5_personas'][$buyerIndex];

        // Extraer datos del buyer persona
        $motivaciones = ! empty($persona['motivaciones']) ? implode(', ', array_slice($persona['motivaciones'], 0, 5)) : 'No especificadas';
        $painPoints = ! empty($persona['pain_points']) ? implode(', ', array_slice($persona['pain_points'], 0, 5)) : 'No especificados';
        $suenos = ! empty($persona['suenos']) ? implode(', ', array_slice($persona['suenos'], 0, 5)) : 'No especificados';
        $objeciones = ! empty($persona['objeciones']) ? implode(', ', array_slice($persona['objeciones'], 0, 3)) : 'No especificadas';
        $keywords = ! empty($persona['keywords']) ? implode(', ', array_slice($persona['keywords'], 0, 10)) : 'No especificadas';
        $canales = ! empty($persona['canales']) ? implode(', ', $persona['canales']) : 'No especificados';

        $context = "
PRODUCTO:
- Nombre: {$productData['nombre']}
- Descripción: {$productData['descripcion']}
- Audiencia Objetivo: {$productData['audiencia_objetivo']}

BUYER PERSONA SELECCIONADO (de {$productData['total_personas']} totales):

PERFIL:
- Nombre: {$persona['nombre']}
- Fuente: {$persona['source_name']}
- Edad: {$persona['edad']}
- Ocupación: {$persona['ocupacion']}
- Descripción: {$persona['descripcion']}

MOTIVACIONES PRINCIPALES:
{$motivaciones}

PAIN POINTS (Problemas/Frustraciones):
{$painPoints}

SUEÑOS/ASPIRACIONES:
{$suenos}

OBJECIONES COMUNES:
{$objeciones}

KEYWORDS QUE USA:
{$keywords}

CANALES PREFERIDOS:
{$canales}
";

        return $context;
    }

    /**
     * Construye el contexto usando todos los datos consolidados
     */
    private function buildConsolidatedContext(array $productData): string
    {
        // Extraer pain points consolidados (top 10)
        $painPoints = collect($productData['pain_points'])
            ->sortByDesc('frecuencia')
            ->take(10)
            ->pluck('texto')
            ->implode(', ');

        // Extraer motivaciones consolidadas (top 10)
        $motivaciones = collect($productData['motivaciones'])
            ->sortByDesc('frecuencia')
            ->take(10)
            ->pluck('texto')
            ->implode(', ');

        // Extraer sueños consolidados (top 10)
        $suenos = collect($productData['suenos'])
            ->sortByDesc('frecuencia')
            ->take(10)
            ->pluck('texto')
            ->implode(', ');

        // Extraer objeciones consolidadas (top 5)
        $objeciones = collect($productData['objeciones'])
            ->sortByDesc('frecuencia')
            ->take(5)
            ->pluck('texto')
            ->implode(', ');

        // Extraer keywords consolidadas (top 15)
        $keywords = collect($productData['keywords'])
            ->sortByDesc('frecuencia')
            ->take(15)
            ->pluck('texto')
            ->implode(', ');

        // Ocupaciones principales
        $ocupaciones = ! empty($productData['ocupaciones_principales'])
            ? implode(', ', $productData['ocupaciones_principales'])
            : 'No especificado';

        $context = "
PRODUCTO:
- Nombre: {$productData['nombre']}
- Descripción: {$productData['descripcion']}
- Audiencia Objetivo: {$productData['audiencia_objetivo']}

DATOS CONSOLIDADOS DE {$productData['total_personas']} BUYER PERSONAS:

DEMOGRAFÍA PROMEDIO:
- Edad Promedio: {$productData['edad_promedio']}
- Rango de Edad: {$productData['edad_rango']}
- Ocupaciones Principales: {$ocupaciones}

MOTIVACIONES PRINCIPALES (consolidadas por frecuencia):
{$motivaciones}

PAIN POINTS (Problemas/Frustraciones más mencionados):
{$painPoints}

SUEÑOS/ASPIRACIONES (más comunes):
{$suenos}

OBJECIONES COMUNES (más frecuentes):
{$objeciones}

KEYWORDS QUE USAN (más relevantes):
{$keywords}
";

        // Agregar insights si están disponibles
        if ($productData['insights_youtube']) {
            $context .= "\n\nINSIGHTS DE YOUTUBE:\n{$productData['insights_youtube']}";
        }

        if ($productData['insights_google_forms']) {
            $context .= "\n\nINSIGHTS DE GOOGLE FORMS:\n{$productData['insights_google_forms']}";
        }

        return $context;
    }

    /**
     * Genera un nombre por defecto para el copy basado en el producto
     */
    private function generateDefaultNameFromProduct($product, string $copyType): string
    {
        $typeName = CopyGeneration::getCopyTypes()[$copyType] ?? $copyType;
        $productName = $product->nombre;
        $date = now()->format('Y-m-d H:i');

        return "{$typeName} - {$productName} - {$date}";
    }

    /**
     * Genera un nombre por defecto para el copy
     */
    private function generateDefaultName($buyerPersona, string $copyType): string
    {
        $typeName = CopyGeneration::getCopyTypes()[$copyType] ?? $copyType;
        $personaName = $buyerPersona->nombre_persona ?? $buyerPersona->nombre ?? 'Buyer';
        $date = now()->format('Y-m-d H:i');

        return "{$typeName} - {$personaName} - {$date}";
    }
}
