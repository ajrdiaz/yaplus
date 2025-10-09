# 🤖 Configuración de Modelos de OpenAI

## 📍 Dónde se Configura el Modelo

El modelo de IA ahora es **configurable desde el archivo `.env`**.

## ⚙️ Configuración Actual

### Archivos involucrados:

#### 1. `.env` (Configuración)
```env
OPENAI_API_KEY=sk-proj-TU_API_KEY_AQUI
OPENAI_MODEL=gpt-4o-mini
```

#### 2. `config/services.php` (Lee desde .env)
```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'), // Valor por defecto
],
```

#### 3. `app/Services/CommentAnalysisService.php` (Usa la config)
```php
public function __construct()
{
    $this->apiKey = config('services.openai.api_key');
    $this->model = config('services.openai.model', 'gpt-4o-mini');
}
```

## 🎯 Modelos Disponibles

### GPT-4 Optimized Series (Recomendados)

#### gpt-4o-mini ⭐ [ACTUAL - Mejor opción para la mayoría]
```env
OPENAI_MODEL=gpt-4o-mini
```
- **Costo**: $0.15 / 1M tokens input, $0.60 / 1M tokens output
- **Velocidad**: ⚡⚡⚡ Muy rápido
- **Calidad**: ⭐⭐⭐⭐ Excelente para análisis
- **Uso**: Perfecto para análisis de comentarios en volumen
- **Contexto**: 128K tokens

#### gpt-4o
```env
OPENAI_MODEL=gpt-4o
```
- **Costo**: $2.50 / 1M tokens input, $10.00 / 1M tokens output
- **Velocidad**: ⚡⚡ Rápido
- **Calidad**: ⭐⭐⭐⭐⭐ Máxima precisión
- **Uso**: Cuando necesitas la máxima calidad
- **Contexto**: 128K tokens

### GPT-4 Turbo Series

#### gpt-4-turbo
```env
OPENAI_MODEL=gpt-4-turbo
```
- **Costo**: $10.00 / 1M tokens input, $30.00 / 1M tokens output
- **Velocidad**: ⚡ Lento
- **Calidad**: ⭐⭐⭐⭐⭐ Máxima
- **Uso**: Solo si necesitas máxima precisión y no importa el costo
- **Contexto**: 128K tokens

#### gpt-4-turbo-preview
```env
OPENAI_MODEL=gpt-4-turbo-preview
```
- Versión preview de GPT-4 Turbo
- Más económico que gpt-4-turbo estándar

### GPT-3.5 Series (Económicos)

#### gpt-3.5-turbo
```env
OPENAI_MODEL=gpt-3.5-turbo
```
- **Costo**: $0.50 / 1M tokens input, $1.50 / 1M tokens output
- **Velocidad**: ⚡⚡⚡ Muy rápido
- **Calidad**: ⭐⭐⭐ Buena
- **Uso**: Cuando el presupuesto es muy limitado
- **Contexto**: 16K tokens

#### gpt-3.5-turbo-16k
```env
OPENAI_MODEL=gpt-3.5-turbo-16k
```
- Igual que gpt-3.5-turbo pero con más contexto
- **Contexto**: 16K tokens

## 💰 Comparación de Costos

### Análisis de 1,000 comentarios (estimado)

Suponiendo ~200 tokens input + ~300 tokens output por comentario:

| Modelo | Costo Total | Por Comentario |
|--------|-------------|----------------|
| **gpt-4o-mini** ⭐ | **~$0.21** | **$0.00021** |
| gpt-3.5-turbo | ~$0.55 | $0.00055 |
| gpt-4o | ~$3.50 | $0.0035 |
| gpt-4-turbo | ~$11.00 | $0.011 |

### Análisis de 10,000 comentarios

| Modelo | Costo Total |
|--------|-------------|
| **gpt-4o-mini** ⭐ | **~$2.10** |
| gpt-3.5-turbo | ~$5.50 |
| gpt-4o | ~$35.00 |
| gpt-4-turbo | ~$110.00 |

## 📊 ¿Qué Modelo Usar?

### Para la mayoría de casos: gpt-4o-mini ⭐
```env
OPENAI_MODEL=gpt-4o-mini
```
**✅ Recomendado porque:**
- Excelente calidad para análisis de comentarios
- 7x más barato que gpt-4o
- Suficientemente rápido
- El más usado por la comunidad

### Para máxima precisión: gpt-4o
```env
OPENAI_MODEL=gpt-4o
```
**Usa cuando:**
- Necesitas máxima precisión en la categorización
- Analizas comentarios muy complejos o ambiguos
- El presupuesto no es problema
- Son pocos comentarios (< 100)

### Para presupuesto limitado: gpt-3.5-turbo
```env
OPENAI_MODEL=gpt-3.5-turbo
```
**Usa cuando:**
- Tienes presupuesto muy limitado
- Los comentarios son simples y directos
- No necesitas análisis muy profundos
- La categorización básica es suficiente

## 🔧 Cómo Cambiar el Modelo

### Paso 1: Edita el archivo `.env`

```env
# Cambia esta línea:
OPENAI_MODEL=gpt-4o-mini

# Por el modelo que desees:
OPENAI_MODEL=gpt-4o
```

### Paso 2: Limpia la caché de configuración

```bash
php artisan config:clear
```

### Paso 3: Verifica el cambio (opcional)

```bash
php artisan tinker
```

Luego en tinker:
```php
config('services.openai.model')
// Debería mostrar: "gpt-4o" (o el modelo que configuraste)
```

### Paso 4: Listo! ✅

El próximo análisis usará el nuevo modelo.

## 🧪 Probar Diferentes Modelos

Puedes probar diferentes modelos y comparar resultados:

### Análisis con gpt-4o-mini (económico)
```env
OPENAI_MODEL=gpt-4o-mini
```
```bash
php artisan youtube:analyze {video_id}
```

### Análisis con gpt-4o (preciso)
```env
OPENAI_MODEL=gpt-4o
```
```bash
php artisan youtube:analyze {video_id}
```

**Compara:**
- Calidad de categorización
- Precisión de insights
- Costo real
- Tiempo de procesamiento

## 📈 Recomendaciones por Caso de Uso

### 🎓 Investigación Académica
**Modelo recomendado:** `gpt-4o`
- Necesitas máxima precisión
- Los datos serán publicados
- Bajo volumen de comentarios

### 💼 Análisis de Negocio
**Modelo recomendado:** `gpt-4o-mini` ⭐
- Balance perfecto calidad/precio
- Alto volumen de comentarios
- Insights accionables suficientes

### 🚀 Prototipo/Testing
**Modelo recomendado:** `gpt-3.5-turbo`
- Solo estás probando funcionalidad
- Bajo presupuesto
- Velocidad > precisión

### 🔍 Análisis Profundo (Pocos Comentarios)
**Modelo recomendado:** `gpt-4o` o `gpt-4-turbo`
- < 100 comentarios críticos
- Cada comentario es muy valioso
- Necesitas máximo detalle

### 📊 Análisis Masivo (Miles de Comentarios)
**Modelo recomendado:** `gpt-4o-mini` ⭐
- > 1,000 comentarios
- Necesitas insights generales
- Presupuesto controlado

## ⚠️ Consideraciones Importantes

### Límites de Rate
Todos los modelos tienen límites de requests por minuto:
- **Tier 1**: 500 requests/min
- **Tier 2**: 5,000 requests/min
- **Tier 3**: 10,000 requests/min

El sistema ya incluye un delay de 0.5 segundos entre requests.

### Timeout
Configurado en 60 segundos por request:
```php
->timeout(60)
```

### Tokens Máximos
Configurado para respuestas de hasta 500 tokens:
```php
'max_tokens' => 500,
```

Si necesitas respuestas más largas, puedes aumentar este valor.

### Temperature
Configurado en 0.7 para balance creatividad/consistencia:
```php
'temperature' => 0.7,
```

- **0.0**: Más determinista (respuestas idénticas)
- **0.7**: Balance recomendado
- **1.0**: Más creativo (puede variar más)

## 🎯 Parámetros Configurables

Si necesitas ajustar otros parámetros, edita `CommentAnalysisService.php`:

```php
->post('https://api.openai.com/v1/chat/completions', [
    'model' => $this->model,
    'messages' => [...],
    'temperature' => 0.7,      // ← Ajustable: 0.0 - 2.0
    'max_tokens' => 500,       // ← Ajustable: 1 - 4096
    'top_p' => 1.0,           // ← Opcional: sampling
    'frequency_penalty' => 0,  // ← Opcional: -2.0 a 2.0
    'presence_penalty' => 0,   // ← Opcional: -2.0 a 2.0
])
```

## 📚 Recursos Adicionales

- [OpenAI Pricing](https://openai.com/pricing)
- [OpenAI Models Documentation](https://platform.openai.com/docs/models)
- [Rate Limits](https://platform.openai.com/docs/guides/rate-limits)
- [Best Practices](https://platform.openai.com/docs/guides/prompt-engineering)

## 🔐 Seguridad

**⚠️ NUNCA** subas el archivo `.env` a Git:
```bash
# Ya está en .gitignore, pero verifica:
git status
# No debería mostrar .env

# Si aparece, agrégalo:
echo ".env" >> .gitignore
```

**✅ API Key guardada en:**
- `.env` (local) ← NO subir a Git
- Variables de entorno del servidor (producción)

---

**¿Dudas?** El modelo actual `gpt-4o-mini` es excelente para el 95% de casos. 
Solo cambia si tienes necesidades específicas. 🚀
