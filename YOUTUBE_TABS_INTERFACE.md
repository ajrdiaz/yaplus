# 📑 Interfaz con Tabs - Gestión de Videos y Comentarios de YouTube

## 🎯 Descripción General

Nueva interfaz mejorada con **tabs (pestañas)** que separa la visualización de videos y comentarios para una mejor organización y experiencia de usuario.

## 🏗️ Estructura de Tabs

### Tab 1: Videos 🎥
Muestra todos los videos importados con sus estadísticas completas:

- **Miniatura del video** (con preview)
- **Título y canal**
- **Estadísticas del video**:
  - 👁️ Vistas
  - 👍 Likes
  - 💬 Total de comentarios en YouTube
- **Comentarios importados**: Badge con cantidad
- **Botón "Ver Comentarios"**: Carga los comentarios del video seleccionado

### Tab 2: Comentarios 💬
Se activa al hacer clic en "Ver Comentarios" de un video:

- **Barra de navegación**: Botón "Atrás" + Título del video + Total de comentarios
- **Tabla de comentarios** con:
  - Avatar y autor
  - Texto del comentario (truncado a 2 líneas)
  - Contador de likes con colores según cantidad
  - Contador de respuestas
  - Botones: Ver detalles y Eliminar

## 🚀 Funcionalidades

### 1. Importar Videos
```javascript
// El formulario de importación permanece arriba
- URL del video
- Límite de comentarios (o checkbox "Importar TODOS")
- Botón "Importar"
```

### 2. Ver Videos
```javascript
// DataTable con paginación
- Muestra 10 videos por página
- Incluye todas las estadísticas del video
- Badge con número de comentarios importados
```

### 3. Cargar Comentarios
```javascript
// Al hacer clic en "Ver Comentarios"
loadVideoComments(video) {
    // Hace request AJAX a: GET /youtube/videos/{video}/comments
    // Cambia automáticamente al Tab de comentarios
    // Muestra ProgressBar mientras carga
}
```

### 4. Ver Detalles de Comentario
```javascript
// Dialog modal con:
- Comentario completo con HTML formateado
- Estadísticas (likes, respuestas)
- Lista de respuestas con avatares y datos
```

### 5. Eliminar Comentario
```javascript
// Confirmación + Request DELETE
// Recarga automáticamente los comentarios del video
```

### 6. Volver a Videos
```javascript
backToVideos() {
    // Regresa al Tab 1
    // Limpia el video seleccionado
    // Limpia los comentarios cargados
}
```

## 📊 Endpoints Nuevos

### GET /youtube/videos/{video}/comments
**Controlador**: `YoutubeController@getVideoComments`

**Response**:
```json
{
    "success": true,
    "video": {
        "id": 1,
        "title": "...",
        "channel_title": "...",
        // ... más datos del video
    },
    "comments": [
        {
            "id": 1,
            "author": "...",
            "text": "...",
            "text_original": "...",
            "like_count": 10,
            "reply_count": 2,
            "replies": [...],
            // ... más datos
        }
    ]
}
```

## 🎨 Componentes PrimeVue Utilizados

### Nuevos componentes agregados:
```javascript
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import Image from 'primevue/image';
import Badge from 'primevue/badge';
```

### Componentes existentes:
- Card
- DataTable + Column
- Button
- InputText
- Dialog
- Avatar
- Tag
- ProgressBar
- Checkbox

## 📁 Archivos Modificados/Creados

### 1. Nuevo Componente Vue
**Archivo**: `resources/js/Pages/Youtube/Index_Tabs.vue`
- Interfaz completamente nueva con tabs
- Sistema de navegación entre videos y comentarios
- Carga dinámica de comentarios vía AJAX

### 2. Controlador Actualizado
**Archivo**: `app/Http/Controllers/External/YoutubeController.php`

**Método modificado**:
```php
public function index()
{
    $videos = YoutubeVideo::withCount('comments')
        ->orderBy('created_at', 'desc')
        ->paginate(20);

    return Inertia::render('Youtube/Index_Tabs', [
        'videos' => $videos,
    ]);
}
```

**Método nuevo**:
```php
public function getVideoComments($videoId)
{
    $video = YoutubeVideo::with(['comments' => function ($query) {
        $query->orderBy('published_at', 'desc');
    }])->findOrFail($videoId);

    return response()->json([
        'success' => true,
        'video' => $video,
        'comments' => $video->comments,
    ]);
}
```

### 3. Ruta Nueva
**Archivo**: `routes/web.php`

```php
Route::get('/videos/{video}/comments', [YoutubeController::class, 'getVideoComments'])
    ->name('video.comments');
```

## 🎯 Flujo de Usuario

1. **Usuario accede a /youtube**
   - Ve el formulario de importación
   - Ve la tabla de videos en Tab 1

2. **Usuario hace clic en "Ver Comentarios" de un video**
   - Sistema carga comentarios vía AJAX
   - Tab cambia automáticamente a Tab 2
   - Muestra barra de navegación con info del video
   - Despliega tabla de comentarios

3. **Usuario puede:**
   - Ver detalles completos de un comentario (Dialog)
   - Ver respuestas anidadas con avatares
   - Eliminar comentarios
   - Regresar a la lista de videos con botón "Atrás"

4. **Usuario hace clic en "Atrás"**
   - Regresa al Tab 1
   - Limpia la selección de video
   - Limpia los comentarios cargados

## 💡 Mejoras de UX

### Indicadores Visuales
- **Badge en Tab**: Muestra cantidad total de videos
- **Badge en botón**: Muestra comentarios importados por video
- **ProgressBar**: Indica cuando se están cargando comentarios
- **Tag disabled**: El Tab de comentarios está deshabilitado hasta seleccionar un video

### Colores por Severidad (Likes)
```javascript
const getSeverity = (likeCount) => {
    if (likeCount >= 100) return 'success';  // Verde
    if (likeCount >= 50) return 'info';      // Azul
    if (likeCount >= 10) return 'warning';   // Naranja
    return 'secondary';                       // Gris
};
```

### Formato de Números
```javascript
const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num;
};
// Ejemplo: 1500 → "1.5K", 1500000 → "1.5M"
```

### Truncado de Texto
```css
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```
Limita los comentarios a 2 líneas en la tabla para mantener el diseño limpio.

## 🔄 Estados de la Interfaz

### Estado Inicial
- Tab 1 activo
- Tabla de videos visible
- Tab 2 deshabilitado

### Estado con Video Seleccionado
- Tab 2 activo
- Barra de navegación visible
- Tabla de comentarios con datos
- Botón "Atrás" funcional

### Estado de Carga
- ProgressBar visible
- Botones deshabilitados durante importación
- ProgressBar en Tab 2 durante carga de comentarios

## 📱 Responsive Design

- **Desktop (>960px)**: Layout completo con todas las columnas
- **Tablet (640px-960px)**: Columnas ajustadas, miniaturas más pequeñas
- **Mobile (<640px)**: Stack vertical, Dialog a 90vw

## 🎓 Ventajas del Nuevo Sistema

✅ **Separación clara** entre videos y comentarios
✅ **Carga bajo demanda** - Solo carga comentarios cuando se necesitan
✅ **Mejor performance** - No carga todos los comentarios de golpe
✅ **Navegación intuitiva** - Tabs + botón "Atrás"
✅ **Estadísticas visibles** - Info del video siempre visible
✅ **Escalable** - Funciona con cientos de videos

## 🧪 Testing

### Probar:
1. ✅ Importar video nuevo
2. ✅ Ver lista de videos con estadísticas
3. ✅ Hacer clic en "Ver Comentarios"
4. ✅ Verificar que carga los comentarios correctamente
5. ✅ Ver detalles de un comentario con respuestas
6. ✅ Eliminar un comentario
7. ✅ Regresar a videos con botón "Atrás"
8. ✅ Cambiar manualmente entre tabs
9. ✅ Verificar paginación en ambas tablas

## 🚀 Próximas Mejoras Sugeridas

- 🔍 **Búsqueda y filtros** en tabla de videos
- 📊 **Gráficos** de estadísticas por video
- 🏷️ **Etiquetas/Tags** para organizar videos
- 💾 **Exportar comentarios** a CSV/Excel
- 🔄 **Sincronización automática** de comentarios nuevos
- 📧 **Notificaciones** por email cuando hay comentarios nuevos
- 🤖 **Análisis de sentimiento** de comentarios
- 🔗 **Relacionar videos** por tema/categoría
