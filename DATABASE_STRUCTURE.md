# Estructura de Base de Datos Mejorada - YouTube

## 📊 Nueva Estructura

La base de datos ahora tiene dos tablas separadas con una relación uno-a-muchos:

### Tabla: `youtube_videos`
Almacena la información de los videos de YouTube.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | ID único |
| video_id | string (único) | ID del video de YouTube |
| title | string | Título del video |
| description | text | Descripción del video |
| channel_id | string | ID del canal |
| channel_title | string | Nombre del canal |
| thumbnail_url | string | URL del thumbnail |
| thumbnail_default | string | Thumbnail tamaño default |
| thumbnail_medium | string | Thumbnail tamaño medium |
| thumbnail_high | string | Thumbnail tamaño high |
| url | string | URL completa del video |
| duration | string | Duración (formato ISO 8601) |
| view_count | bigint | Número de vistas |
| like_count | bigint | Número de likes |
| comment_count | bigint | Número de comentarios |
| published_at | timestamp | Fecha de publicación |
| created_at | timestamp | Fecha de creación |
| updated_at | timestamp | Fecha de actualización |

### Tabla: `youtube_comments`
Almacena los comentarios de los videos.

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | ID único |
| youtube_video_id | bigint (FK) | Foreign key a youtube_videos |
| video_id | string | ID del video (redundante para búsquedas) |
| comment_id | string (único) | ID único del comentario |
| author | string | Nombre del autor |
| author_image | string | URL de la imagen del autor |
| text | text | Comentario con formato HTML |
| text_original | text | Comentario sin formato |
| like_count | integer | Cantidad de likes |
| reply_count | integer | Cantidad de respuestas |
| published_at | timestamp | Fecha de publicación |
| comment_updated_at | timestamp | Última actualización |
| replies | json | Array de respuestas |
| created_at | timestamp | Fecha de importación |
| updated_at | timestamp | Última actualización local |

## 🔗 Relaciones

### YoutubeVideo → YoutubeComment (Uno a Muchos)
```php
// En el modelo YoutubeVideo
public function comments(): HasMany
{
    return $this->hasMany(YoutubeComment::class);
}

// Uso:
$video = YoutubeVideo::with('comments')->find(1);
$comments = $video->comments;
```

### YoutubeComment → YoutubeVideo (Muchos a Uno)
```php
// En el modelo YoutubeComment
public function video(): BelongsTo
{
    return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
}

// Uso:
$comment = YoutubeComment::with('video')->find(1);
$videoTitle = $comment->video->title;
```

## ✅ Ventajas de esta estructura

1. **Normalización**: Evita redundancia de datos del video
2. **Consistencia**: Un solo lugar para actualizar información del video
3. **Eficiencia**: Menos espacio en disco
4. **Integridad**: Foreign keys garantizan integridad referencial
5. **Escalabilidad**: Fácil agregar más campos al video sin afectar comentarios
6. **Queries optimizadas**: Usar eager loading para obtener video + comentarios

## 📝 Ejemplos de Uso

### Obtener todos los comentarios de un video
```php
$video = YoutubeVideo::where('video_id', 'dQw4w9WgXcQ')->first();
$comments = $video->comments()->orderBy('like_count', 'desc')->get();
```

### Obtener video con sus comentarios
```php
$video = YoutubeVideo::with('comments')
    ->where('video_id', 'dQw4w9WgXcQ')
    ->first();
```

### Contar comentarios por video
```php
$videos = YoutubeVideo::withCount('comments')
    ->orderBy('comments_count', 'desc')
    ->get();
```

### Obtener videos más comentados
```php
$topVideos = YoutubeVideo::withCount('comments')
    ->orderBy('comments_count', 'desc')
    ->limit(10)
    ->get();
```

### Buscar comentarios con información del video
```php
$comments = YoutubeComment::with('video')
    ->where('like_count', '>', 100)
    ->get();

foreach ($comments as $comment) {
    echo $comment->video->title;
    echo $comment->text_original;
}
```

## 🔄 Migración de Datos Existentes

Si ya tenías comentarios con la estructura antigua, puedes migrarlos así:

```php
// Ejecutar en tinker o crear un comando
$comments = YoutubeComment::whereNull('youtube_video_id')->get();

foreach ($comments as $comment) {
    // Buscar o crear el video
    $video = YoutubeVideo::firstOrCreate(
        ['video_id' => $comment->video_id],
        [
            'title' => $comment->video_title,
            'url' => $comment->video_url,
            'channel_title' => 'Desconocido',
            'published_at' => now(),
        ]
    );
    
    // Actualizar el comentario
    $comment->youtube_video_id = $video->id;
    $comment->save();
}
```

## 🎯 Queries Útiles

### Dashboard de Estadísticas
```php
$stats = [
    'total_videos' => YoutubeVideo::count(),
    'total_comments' => YoutubeComment::count(),
    'total_views' => YoutubeVideo::sum('view_count'),
    'total_likes' => YoutubeVideo::sum('like_count'),
    'avg_comments_per_video' => YoutubeComment::count() / YoutubeVideo::count(),
];
```

### Videos con más engagement
```php
$engagedVideos = YoutubeVideo::orderByDesc('like_count')
    ->orderByDesc('view_count')
    ->limit(10)
    ->get();
```

### Comentarios recientes con video
```php
$recentComments = YoutubeComment::with('video')
    ->orderByDesc('published_at')
    ->paginate(20);
```

## 🚀 Importación Automática

Cuando importas comentarios, el sistema:

1. ✅ Verifica si el video existe en la BD
2. ✅ Si existe: actualiza sus estadísticas
3. ✅ Si no existe: lo crea con toda su información
4. ✅ Asocia cada comentario al video mediante `youtube_video_id`
5. ✅ Evita duplicados de comentarios por `comment_id`

## 📄 Migraciones Ejecutadas

1. `2025_10_08_123107_create_youtube_comments_table.php`
2. `2025_10_08_124905_create_youtube_videos_table.php`
3. `2025_10_08_124930_modify_youtube_comments_table_add_video_relation.php`

## 🎨 Acceso desde Vue/Inertia

```javascript
// Los comentarios ahora incluyen la relación video
comments.data.forEach(comment => {
    console.log(comment.video.title);
    console.log(comment.video.channel_title);
    console.log(comment.video.view_count);
    console.log(comment.video.thumbnail_high);
});
```

## 🔍 Búsquedas Avanzadas

### Buscar por canal
```php
$comments = YoutubeComment::whereHas('video', function($query) {
    $query->where('channel_title', 'LIKE', '%Laravel%');
})->get();
```

### Videos publicados en un rango de fechas
```php
$videos = YoutubeVideo::whereBetween('published_at', [
    now()->subMonths(3),
    now()
])->get();
```

### Comentarios de videos específicos
```php
$comments = YoutubeComment::whereHas('video', function($query) {
    $query->whereIn('video_id', ['abc123', 'def456']);
})->get();
```

---

Esta estructura es mucho más profesional y escalable que la anterior. ¡Ahora puedes gestionar múltiples videos con sus comentarios de manera eficiente! 🎉
