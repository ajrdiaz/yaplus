<?php

namespace App\Services;

use App\Models\CopyGeneration;
use OpenAI\Laravel\Facades\OpenAI;

class CopyGeneratorService
{
    /**
     * Genera copy basado en un buyer persona
     */
    public function generateCopy($buyerPersona, string $copyType, ?string $customName = null): CopyGeneration
    {
        $buyerData = $this->prepareBuyerPersonaData($buyerPersona);
        $prompt = $this->buildPrompt($buyerData, $copyType);

        $response = OpenAI::chat()->create([
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
        ]);

        $generatedContent = $response->choices[0]->message->content;
        $parsedContent = $this->parseGeneratedContent($generatedContent, $copyType);

        // Guardar en base de datos
        $copy = CopyGeneration::create([
            'buyer_persona_id' => $buyerPersona->id,
            'buyer_persona_type' => get_class($buyerPersona),
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
    private function buildPrompt(array $buyerData, string $copyType): string
    {
        $baseContext = $this->buildBaseContext($buyerData);

        $prompts = [
            'facebook_ad' => "
{$baseContext}

Crea un anuncio para Facebook/Instagram Ads que:
1. Tenga un HEADLINE impactante (máximo 40 caracteres) que capte atención usando su principal pain point
2. Tenga un TEXT/BODY persuasivo (máximo 125 caracteres) que mencione el beneficio principal conectado a sus sueños
3. Incluya un CALL TO ACTION claro y accionable (máximo 30 caracteres)
4. Use lenguaje directo, emocional y las keywords que ellos usan

Formato de respuesta:
HEADLINE: [texto]
BODY: [texto]
CTA: [texto]
",

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
    private function buildBaseContext(array $buyerData): string
    {
        $motivaciones = ! empty($buyerData['motivaciones']) ? implode(', ', array_slice($buyerData['motivaciones'], 0, 5)) : 'No especificadas';
        $painPoints = ! empty($buyerData['pain_points']) ? implode(', ', array_slice($buyerData['pain_points'], 0, 5)) : 'No especificados';
        $suenos = ! empty($buyerData['suenos']) ? implode(', ', array_slice($buyerData['suenos'], 0, 5)) : 'No especificados';
        $objeciones = ! empty($buyerData['objeciones']) ? implode(', ', array_slice($buyerData['objeciones'], 0, 3)) : 'No especificadas';
        $keywords = ! empty($buyerData['keywords']) ? implode(', ', array_slice($buyerData['keywords'], 0, 10)) : 'No especificadas';

        return "
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
    }

    /**
     * Parsea el contenido generado según el tipo
     */
    private function parseGeneratedContent(string $content, string $copyType): array
    {
        $parsed = [];

        switch ($copyType) {
            case 'facebook_ad':
                preg_match('/HEADLINE:\s*(.+?)(?:\n|$)/i', $content, $headline);
                preg_match('/BODY:\s*(.+?)(?:\n(?:CTA:|$)|$)/is', $content, $body);
                preg_match('/CTA:\s*(.+?)(?:\n|$)/i', $content, $cta);

                $parsed['headline'] = trim($headline[1] ?? '');
                $parsed['body'] = trim($body[1] ?? '');
                $parsed['cta'] = trim($cta[1] ?? '');
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
