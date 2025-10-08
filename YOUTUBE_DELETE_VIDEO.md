# 🗑️ Eliminar Videos con Todas sus Relaciones

## 🎯 Funcionalidad

Ahora puedes **eliminar videos completos** junto con todos sus datos relacionados desde la interfaz web.

## 📍 Ubicación

En la página `/youtube`, Tab **"Videos"**, columna de **"Acciones"**.

## 🔴 Botón de Eliminación

Cada video tiene un **botón de basura** (🗑️) rojo al final de la fila.

```
┌─────────────────────────────────────────────────────┐
│ Video | Estadísticas | Comentarios | Acciones       │
├─────────────────────────────────────────────────────┤
│ ...   | ...          | 50          | [Comentarios]  │
│                                      [Analizar IA]  │
│                                      [Ver Análisis] │
│                                      [🗑️]           │
└─────────────────────────────────────────────────────┘
```

## ⚠️ Confirmación de Seguridad

Al hacer clic en el botón de eliminar, verás un diálogo de confirmación:

```
¿Estás seguro de eliminar el video "Título del Video"?

Esto eliminará:
- El video
- 50 comentarios
- Todos los análisis asociados

Esta acción no se puede deshacer.

[Cancelar] [Aceptar]
```

## 🔗 Eliminación en Cascada

El sistema elimina automáticamente **TODO** lo relacionado con el video:

### 1. Video (youtube_videos)
```sql
DELETE FROM youtube_videos WHERE id = X
```

### 2. Comentarios (youtube_comments) - Automático
```sql
-- Se eliminan automáticamente por CASCADE
-- Gracias a: onDelete('cascade')
DELETE FROM youtube_comments WHERE youtube_video_id = X
```

### 3. Análisis IA (youtube_comment_analysis) - Automático
```sql
-- Se eliminan automáticamente por CASCADE
DELETE FROM youtube_comment_analysis WHERE youtube_video_id = X
DELETE FROM youtube_comment_analysis WHERE youtube_comment_id IN (...)
```

## 📊 Flujo Técnico

### Base de Datos

Las migraciones tienen configurado `onDelete('cascade')`:

**youtube_comments:**
```php
$table->foreignId('youtube_video_id')
    ->constrained('youtube_videos')
    ->onDelete('cascade');
```

**youtube_comment_analysis:**
```php
$table->foreignId('youtube_comment_id')
    ->constrained('youtube_comments')
    ->onDelete('cascade');

$table->foreignId('youtube_video_id')
    ->constrained('youtube_videos')
    ->onDelete('cascade');
```

### Controlador

**Método:** `YoutubeController@destroyVideo`

```php
public function destroyVideo($id)
{
    $video = YoutubeVideo::findOrFail($id);
    
    $videoTitle = $video->title;
    $commentsCount = $video->comments()->count();

    // Eliminar el video (cascade hace el resto)
    $video->delete();

    return response()->json([
        'success' => true,
        'message' => "Video '{$videoTitle}' eliminado con {$commentsCount} comentarios",
    ]);
}
```

### Ruta

```php
Route::delete('/videos/{video}', [YoutubeController::class, 'destroyVideo'])
    ->name('youtube.video.destroy');
```

### Vue Component

**Función:** `deleteVideo(video)`

```javascript
const deleteVideo = (video) => {
    // Confirmación detallada
    if (!confirm(`¿Eliminar "${video.title}"?\n\nEsto eliminará:\n- El video\n- ${video.comments_count} comentarios\n- Todos los análisis`)) {
        return;
    }

    // Request DELETE
    axios.delete(route('youtube.video.destroy', video.id))
        .then(response => {
            toast.add({
                severity: 'success',
                summary: 'Video Eliminado',
                detail: response.data.message,
                life: 5000
            });
            
            // Recargar lista
            router.reload({ only: ['videos'] });
            
            // Limpiar selección si era el video actual
            if (selectedVideo.value?.id === video.id) {
                backToVideos();
            }
        });
};
```

## 🎯 Casos de Uso

### 1. Videos de Prueba
Elimina videos que importaste solo para probar:
- Click en 🗑️
- Confirmar
- ✅ Video y todos sus datos eliminados

### 2. Videos Irrelevantes
Si importaste un video que no era útil para tu investigación:
- Elimínalo sin dejar rastro
- Ahorra espacio en la base de datos

### 3. Limpieza de Datos
Mantén solo los videos relevantes:
- Elimina videos antiguos
- Elimina videos con pocos comentarios útiles

## 📋 Ejemplo Práctico

### Antes de Eliminar:
```sql
-- Base de datos
youtube_videos: 10 registros
youtube_comments: 500 registros (50 por video)
youtube_comment_analysis: 250 registros
```

### Usuario Elimina Video #5:
```
1. Click en 🗑️ del video #5
2. Confirma eliminación
3. Sistema ejecuta: DELETE FROM youtube_videos WHERE id = 5
```

### Después de Eliminar:
```sql
-- Base de datos (automático por CASCADE)
youtube_videos: 9 registros (eliminado #5)
youtube_comments: 450 registros (eliminados 50 del video #5)
youtube_comment_analysis: 225 registros (eliminados 25 del video #5)
```

## 🔒 Seguridad

### Validación del Backend
```php
$video = YoutubeVideo::findOrFail($id);
// Si no existe, lanza 404
```

### Confirmación del Frontend
- Diálogo de confirmación obligatorio
- Muestra cantidad exacta de datos a eliminar
- Advierte que la acción es irreversible

### Logs
Todos los errores se registran:
```php
Log::error('Error al eliminar video', [
    'video_id' => $id,
    'error' => $e->getMessage()
]);
```

## ⚡ Rendimiento

### Eliminación Rápida
Gracias a `onDelete('cascade')`:
- Una sola query DELETE al video
- La base de datos elimina el resto automáticamente
- Más rápido que eliminar manualmente cada relación

### Sin Queries Adicionales
```php
// ❌ NO necesitas hacer:
$video->comments()->delete();
$video->analysis()->delete();
$video->delete();

// ✅ Solo necesitas:
$video->delete(); // Cascade hace el resto
```

## 🎨 Interfaz

### Botón de Eliminar
```vue
<Button
    icon="pi pi-trash"
    size="small"
    severity="danger"
    @click="deleteVideo(data)"
    v-tooltip.top="'Eliminar video y todos sus datos'"
/>
```

**Características:**
- Icono de basura (pi-trash)
- Color rojo (severity="danger")
- Tooltip informativo
- Tamaño pequeño para no saturar

### Toast de Confirmación
```javascript
toast.add({
    severity: 'success',
    summary: 'Video Eliminado',
    detail: "Video 'Título' eliminado con 50 comentarios",
    life: 5000
});
```

## 📱 Responsive

El botón de eliminar se adapta a diferentes pantallas:
- **Desktop**: Botón completo visible
- **Tablet**: Botón con solo icono
- **Mobile**: Botón stackeado verticalmente con los demás

## ⚠️ Precauciones

### Antes de Eliminar
1. **Verifica el video**: Asegúrate de que es el correcto
2. **Revisa comentarios**: Puede tener análisis valiosos
3. **Exporta datos**: Si necesitas respaldo (próximamente)

### No se Puede Deshacer
- Una vez eliminado, **NO hay forma de recuperarlo**
- Los comentarios y análisis también se pierden
- Considera hacer un backup de la base de datos periódicamente

## 🔄 Actualización de la UI

Después de eliminar:
1. **Lista de videos** se recarga automáticamente
2. Si estabas viendo comentarios del video eliminado, **vuelves al Tab Videos**
3. **Toast de confirmación** muestra mensaje de éxito
4. **Contador de videos** se actualiza en el Badge del Tab

## 🧪 Testing

### Probar la Funcionalidad:
1. Importa un video de prueba
2. Ve al Tab "Videos"
3. Haz clic en el botón 🗑️
4. Confirma la eliminación
5. Verifica que:
   - El video desaparece de la lista
   - Los comentarios ya no están en la BD
   - Los análisis también fueron eliminados

### Verificar en Base de Datos:
```sql
-- Contar registros antes
SELECT COUNT(*) FROM youtube_videos;
SELECT COUNT(*) FROM youtube_comments;
SELECT COUNT(*) FROM youtube_comment_analysis;

-- Eliminar un video desde la interfaz

-- Contar registros después
-- Los números deben haber disminuido correctamente
```

## 📈 Próximas Mejoras

- [ ] **Soft Delete**: Eliminar lógicamente en lugar de físicamente
- [ ] **Papelera**: Recuperar videos eliminados en los últimos 30 días
- [ ] **Exportar antes de eliminar**: Descargar datos como CSV antes de borrar
- [ ] **Eliminar múltiples videos**: Checkbox para selección masiva
- [ ] **Confirmación doble**: Para videos con muchos comentarios (>100)

---

**¡Ahora puedes mantener tu base de datos limpia eliminando videos de prueba!** 🎉
