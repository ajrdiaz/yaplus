# YouTube Comments - Guía de Uso

## 📋 Descripción

Sistema completo para importar, visualizar y gestionar comentarios de videos de YouTube en tu aplicación Laravel.

## 🚀 Características

- ✅ Importar comentarios desde cualquier video de YouTube
- ✅ Guardar comentarios en base de datos
- ✅ Ver detalles completos de cada comentario
- ✅ Visualizar respuestas anidadas
- ✅ Eliminar comentarios
- ✅ Estadísticas de likes y respuestas
- ✅ Interfaz moderna con Vue 3 y PrimeVue
- ✅ Paginación automática
- ✅ Detección automática del ID del video

## 📁 Archivos Creados

```
app/
├── Http/Controllers/External/
│   └── YoutubeController.php       # Controlador principal
├── Models/
│   └── YoutubeComment.php          # Modelo de comentarios

database/
└── migrations/
    └── 2025_10_08_123107_create_youtube_comments_table.php

resources/
└── js/
    └── Pages/
        └── Youtube/
            └── Index.vue           # Interfaz Vue

routes/
├── web.php                         # Rutas web
└── api.php                         # Rutas API

config/
└── services.php                    # Configuración API Key
```

## ⚙️ Configuración

### 1. API Key de YouTube

Edita tu archivo `.env` y agrega:

```env
YOUTUBE_API_KEY=tu_api_key_aqui
```

### 2. Migración

Ya ejecutada, pero si necesitas ejecutarla nuevamente:

```bash
php artisan migrate
```

## 🎯 Uso

### Desde la Interfaz Web

1. **Acceder al sistema:**
   - Ingresa a tu aplicación
   - En el menú lateral, busca "Herramientas Externas" → "YouTube"

2. **Importar comentarios:**
   - Copia la URL de un video de YouTube
   - Pégala en el campo "URL del video"
   - Selecciona la cantidad de comentarios (máx: 100)
   - Haz clic en "Importar"

3. **Ver comentarios:**
   - Los comentarios aparecerán en la tabla
   - Haz clic en el ícono del ojo 👁️ para ver detalles completos
   - Puedes ver respuestas anidadas en el diálogo

4. **Eliminar comentarios:**
   - Haz clic en el ícono de papelera 🗑️
   - Confirma la eliminación

### Formatos de URL Soportados

```
https://www.youtube.com/watch?v=VIDEO_ID
https://youtu.be/VIDEO_ID
https://www.youtube.com/embed/VIDEO_ID
https://www.youtube.com/v/VIDEO_ID
VIDEO_ID (directo)
```

## 🛠️ API Endpoints

### Web Routes (con autenticación)

```
GET  /youtube                    # Página principal
POST /youtube/import             # Importar comentarios
DELETE /youtube/comments/{id}    # Eliminar comentario
GET  /youtube/stats              # Estadísticas
```

### API Routes (sin autenticación por defecto)

```
GET  /api/youtube/comments       # Obtener comentarios de la API
GET  /api/youtube/video-info     # Info del video
GET  /api/youtube/search         # Buscar videos
```

## 📊 Estructura de Base de Datos

### Tabla: `youtube_comments`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | ID único |
| video_id | string | ID del video de YouTube |
| video_title | string | Título del video |
| video_url | string | URL del video |
| comment_id | string | ID único del comentario (único) |
| author | string | Nombre del autor |
| author_image | string | URL de la imagen del autor |
| text | text | Comentario con formato HTML |
| text_original | text | Comentario sin formato |
| like_count | integer | Cantidad de likes |
| reply_count | integer | Cantidad de respuestas |
| published_at | timestamp | Fecha de publicación |
| comment_updated_at | timestamp | Última actualización |
| replies | json | Respuestas anidadas |
| created_at | timestamp | Fecha de importación |
| updated_at | timestamp | Última actualización local |

## 💡 Ejemplos de Código

### Importar comentarios programáticamente

```php
use App\Http\Controllers\External\YoutubeController;

$controller = new YoutubeController();
$request = new Request([
    'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'max_results' => 50
]);

$response = $controller->importComments($request);
```

### Consultar comentarios desde otro controlador

```php
use App\Models\YoutubeComment;

// Obtener todos los comentarios de un video
$comments = YoutubeComment::where('video_id', 'VIDEO_ID')->get();

// Obtener comentarios más populares
$popularComments = YoutubeComment::orderBy('like_count', 'desc')->take(10)->get();

// Obtener estadísticas
$stats = [
    'total' => YoutubeComment::count(),
    'total_likes' => YoutubeComment::sum('like_count'),
    'videos' => YoutubeComment::distinct('video_id')->count(),
];
```

### Usar desde Vue (Inertia)

```javascript
import { router } from '@inertiajs/vue3';
import axios from 'axios';

// Importar comentarios
const importComments = async () => {
    const response = await axios.post(route('youtube.import'), {
        video_url: 'https://www.youtube.com/watch?v=VIDEO_ID',
        max_results: 50
    });
    
    console.log(response.data);
};

// Eliminar comentario
const deleteComment = async (commentId) => {
    await axios.delete(route('youtube.destroy', commentId));
    router.reload();
};
```

## 🔒 Seguridad

- ✅ Todas las rutas web requieren autenticación
- ✅ Validación de URLs
- ✅ Prevención de duplicados (por `comment_id`)
- ✅ Rate limiting de la API de YouTube
- ✅ Logs de errores

## 📈 Mejoras Futuras Sugeridas

1. **Caché**: Implementar caché de comentarios
2. **Análisis de Sentimiento**: Analizar si los comentarios son positivos/negativos
3. **Exportar**: Exportar comentarios a CSV/Excel
4. **Búsqueda**: Búsqueda por texto dentro de comentarios
5. **Filtros**: Filtrar por fecha, likes, autor
6. **Actualización**: Actualizar comentarios existentes
7. **Scraping**: Alternativa con scraping cuando no hay API Key
8. **Webhooks**: Notificaciones cuando hay nuevos comentarios
9. **Moderación**: Marcar comentarios como spam/inapropiados
10. **Dashboard**: Gráficos de estadísticas

## 🐛 Solución de Problemas

### Error: "Config [services.youtube.api_key] not found"

**Solución:** Verifica que hayas agregado `YOUTUBE_API_KEY` en tu archivo `.env`

### Error: "URL de video inválida"

**Solución:** Asegúrate de usar un formato válido de URL de YouTube

### Error: "Quota exceeded"

**Solución:** Has excedido la cuota diaria de la API de YouTube (10,000 unidades/día)

### Los comentarios no se guardan

**Solución:** 
- Verifica que la migración se haya ejecutado
- Revisa los logs en `storage/logs/laravel.log`

## 📞 Soporte

Para más información:
- [Documentación de YouTube API](https://developers.google.com/youtube/v3/docs)
- [PrimeVue Components](https://primevue.org/)
- [Inertia.js](https://inertiajs.com/)

## 📝 Licencia

Este código es parte de tu aplicación Laravel y sigue la misma licencia del proyecto.
