# 📝 Estructura de Respuestas en YouTube

## ❓ Pregunta: ¿Las respuestas de YouTube solo son de 1 nivel?

**Respuesta: SÍ** ✅

## 🏗️ Estructura de Comentarios de YouTube

YouTube utiliza una estructura de **2 niveles únicamente**:

```
Video
├── Comentario Principal (Top-Level Comment)
│   ├── Respuesta 1 (Reply)
│   ├── Respuesta 2 (Reply)
│   ├── Respuesta 3 (Reply)
│   └── ...
├── Comentario Principal 2
│   ├── Respuesta 1
│   └── Respuesta 2
└── ...
```

### 🎯 Niveles Permitidos

1. **Nivel 1 - Comentario Principal (Comment Thread)**
   - Es el comentario original que se hace directamente en el video
   - Se obtiene mediante el endpoint: `commentThreads`
   - Tiene un ID único del tipo: `Ugz9vib2aUKVlFYS2Zx4AaABAg`

2. **Nivel 2 - Respuestas (Replies)**
   - Son respuestas directas al comentario principal
   - Se incluyen dentro del `commentThread` en el campo `replies`
   - **NO se pueden hacer respuestas a respuestas**
   - Todas las "conversaciones" aparecen como respuestas al comentario principal

## 🔍 Ejemplo Real

### Comentario de la Base de Datos:

```json
{
  "id": 9,
  "author": "@JorgeFranco-e2v",
  "text": "Comentario principal...",
  "reply_count": 1,
  "replies": [
    {
      "id": "Ugz9vib2aUKVlFYS2Zx4AaABAg.AO-DL8YrrMCAO-GMHsiJ-R",
      "author": "@VladimirValico",
      "author_image": "https://...",
      "text": "Claro el burrito saldrá el 2052 no el 2025 😂😂😂😂",
      "like_count": 1,
      "published_at": "2025-10-08T00:04:15Z"
    }
  ]
}
```

### Cómo se Ve en YouTube:

```
┌─────────────────────────────────────────────┐
│ @JorgeFranco-e2v                            │
│ Comentario principal...                     │
│ 👍 10 likes  💬 1 respuesta                 │
│                                             │
│   ├── @VladimirValico                      │
│   │   Claro el burrito saldrá el 2052...   │
│   │   👍 1 like                             │
└─────────────────────────────────────────────┘
```

## 🔄 ¿Qué pasa si alguien responde a una respuesta?

En la interfaz de YouTube, si haces clic en "Responder" en una respuesta, tu nuevo comentario **TAMBIÉN aparece como respuesta del comentario principal**, no como respuesta anidada.

### Ejemplo:
```
Usuario A: "Gran video!"
├── Usuario B: "Estoy de acuerdo"
└── Usuario C: "@Usuario B totalmente"  ← Aparece aquí, no anidado bajo B
```

Aunque Usuario C mencionó a Usuario B con `@Usuario B`, la respuesta se guarda al mismo nivel que la respuesta de B.

## 📊 API de YouTube - commentThreads

### Endpoint:
```
GET https://www.googleapis.com/youtube/v3/commentThreads
```

### Parámetros:
- `part=snippet,replies` ← Importante incluir "replies"
- `videoId={VIDEO_ID}`
- `key={API_KEY}`

### Response Structure:
```json
{
  "items": [
    {
      "id": "COMMENT_THREAD_ID",
      "snippet": {
        "topLevelComment": {
          "snippet": {
            "authorDisplayName": "...",
            "textDisplay": "...",
            "likeCount": 10,
            ...
          }
        },
        "totalReplyCount": 5
      },
      "replies": {
        "comments": [
          {
            "id": "REPLY_ID_1",
            "snippet": {
              "authorDisplayName": "...",
              "textDisplay": "...",
              "likeCount": 2,
              ...
            }
          },
          {
            "id": "REPLY_ID_2",
            "snippet": { ... }
          }
        ]
      }
    }
  ]
}
```

## 💾 Cómo lo Almacenamos

### Tabla: `youtube_comments`

```sql
CREATE TABLE youtube_comments (
    id BIGINT PRIMARY KEY,
    comment_id VARCHAR(255) UNIQUE,  -- ID del comentario principal
    author VARCHAR(255),
    text TEXT,
    like_count INT,
    reply_count INT,                 -- Número total de respuestas
    replies LONGTEXT,                -- JSON con todas las respuestas
    ...
);
```

### Campo `replies` (JSON):
```json
[
  {
    "id": "REPLY_1",
    "author": "@User1",
    "author_image": "https://...",
    "text": "Respuesta 1",
    "like_count": 5,
    "published_at": "2025-10-08T00:00:00Z"
  },
  {
    "id": "REPLY_2",
    "author": "@User2",
    "text": "Respuesta 2",
    "like_count": 3,
    "published_at": "2025-10-08T01:00:00Z"
  }
]
```

## 🎨 Cómo lo Mostramos en la Interfaz

### En el DataTable:
```vue
<Column field="reply_count" header="Respuestas">
    <template #body="{ data }">
        <Tag v-if="data.reply_count > 0" severity="secondary" rounded>
            <i class="pi pi-reply mr-1"></i>
            {{ data.reply_count }}
        </Tag>
    </template>
</Column>
```

### En el Dialog (Ver Detalles):
```vue
<div v-if="selectedComment.replies && selectedComment.replies.length > 0">
    <h3>Respuestas ({{ selectedComment.replies.length }})</h3>
    <div v-for="reply in selectedComment.replies" :key="reply.id">
        <!-- Avatar + Autor + Texto -->
        <!-- NO hay sub-respuestas aquí -->
    </div>
</div>
```

## ⚠️ Limitaciones

### 1. Solo 2 Niveles
- **Comentario Principal** → Puede tener respuestas
- **Respuestas** → NO pueden tener sub-respuestas
- No existe estructura de "árbol" profundo

### 2. Menciones vs Anidación
- Si alguien usa `@Usuario` en una respuesta, es solo texto
- No crea una relación de anidación real
- Todas las respuestas están al mismo nivel

### 3. API Responses
- La API devuelve todas las respuestas en un array plano
- No hay jerarquía adicional
- El orden es cronológico (más antiguo primero)

## 🆚 Comparación con Otras Plataformas

| Plataforma | Niveles de Respuestas |
|------------|----------------------|
| **YouTube** | 2 niveles (Comentario + Respuestas) |
| **Reddit** | Infinitos niveles anidados |
| **Twitter/X** | 2 niveles (Tweet + Replies) |
| **Facebook** | 2 niveles (Post + Comments) |
| **Instagram** | 2 niveles (Post + Comments) |

## 📌 Conclusión

✅ **Sí, las respuestas de YouTube son solo de 1 nivel** (respecto al comentario principal)

✅ **Estructura**: Comentario Principal → Respuestas (sin sub-respuestas)

✅ **Almacenamiento**: Guardamos las respuestas como JSON array en el campo `replies`

✅ **Visualización**: Mostramos las respuestas como una lista plana bajo el comentario principal

## 🔧 Código Relevante

### Controller - formatReplies():
```php
private function formatReplies($replies): array
{
    if (!$replies || !isset($replies['comments'])) {
        return [];
    }

    return collect($replies['comments'])->map(function ($reply) {
        $snippet = $reply['snippet'];
        
        return [
            'id' => $reply['id'],
            'author' => $snippet['authorDisplayName'],
            'author_image' => $snippet['authorProfileImageUrl'],
            'text' => $snippet['textDisplay'],
            'like_count' => $snippet['likeCount'],
            'published_at' => $snippet['publishedAt'],
        ];
    })->toArray();
}
```

### Vue Component - Mostrar Respuestas:
```vue
<div v-for="reply in selectedComment.replies" :key="reply.id">
    <Avatar :image="reply.author_image" />
    <div>
        <div class="font-semibold">{{ reply.author }}</div>
        <div v-html="reply.text"></div>
        <span>👍 {{ reply.like_count }}</span>
    </div>
</div>
```

## 🚀 Funcionalidad Actual

Nuestro sistema **ya maneja correctamente** esta estructura:

✅ Importa comentarios principales
✅ Importa todas las respuestas de cada comentario
✅ Almacena respuestas como JSON
✅ Muestra contador de respuestas en la tabla
✅ Despliega todas las respuestas en el dialog de detalles
✅ Formatea respuestas con avatar, autor, texto y likes

**No se requieren cambios** porque YouTube no soporta más niveles de anidación. 🎯
