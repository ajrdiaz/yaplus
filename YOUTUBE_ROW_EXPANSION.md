# 📖 Row Expansion - Visualización de Respuestas en DataTable

## 🎯 Descripción

Implementación de **Row Expansion** en PrimeVue para mostrar las respuestas de comentarios de YouTube directamente en la tabla, sin necesidad de abrir un modal.

## ✨ Características

### ✅ Ventajas de Row Expansion vs Modal

| Característica | Row Expansion | Modal |
|---------------|---------------|-------|
| **Flujo de trabajo** | Continuo, sin interrupción | Interrumpe el flujo |
| **Navegación** | Expande/colapsa en la misma tabla | Requiere abrir/cerrar |
| **Múltiples comentarios** | Puede expandir varios a la vez | Solo uno visible |
| **Scroll** | Mantiene el contexto de la tabla | Nuevo contexto |
| **Espacio visual** | Utiliza el ancho completo de la tabla | Ventana flotante |
| **UX** | Más fluido y rápido | Más formal |

## 🏗️ Estructura de Implementación

### 1. Variables Reactivas

```javascript
const expandedRows = ref([]);  // Array de filas expandidas
```

### 2. DataTable con Expansion

```vue
<DataTable
    :value="videoComments"
    v-model:expandedRows="expandedRows"  // Binding para controlar expansión
    dataKey="id"
    :paginator="true"
    :rows="10"
>
    <!-- Columna especial para el botón de expansión -->
    <Column :expander="true" style="width: 3rem" />
    
    <!-- Resto de columnas... -->
    
    <!-- Template de contenido expandido -->
    <template #expansion="{ data }">
        <!-- Contenido aquí -->
    </template>
</DataTable>
```

## 📋 Componentes Utilizados

### Columna Expander
```vue
<Column :expander="true" style="width: 3rem" />
```
- Agrega un botón de expansión (chevron) en cada fila
- Automáticamente maneja el estado expandido/colapsado
- Ancho fijo de 3rem (icono + padding)

### Template Expansion
```vue
<template #expansion="{ data }">
    <div class="p-3">
        <!-- Comentario completo -->
        <div class="mb-4">
            <h4>Comentario Completo</h4>
            <div v-html="data.text"></div>
        </div>

        <!-- Respuestas -->
        <div v-if="data.reply_count > 0">
            <h4>Respuestas ({{ data.reply_count }})</h4>
            <div v-for="reply in parseReplies(data.replies)" :key="reply.id">
                <!-- Contenido de respuesta -->
            </div>
        </div>
    </div>
</template>
```

## 🔧 Función parseReplies()

```javascript
const parseReplies = (repliesJson) => {
    if (!repliesJson) return [];
    try {
        return typeof repliesJson === 'string' 
            ? JSON.parse(repliesJson) 
            : repliesJson;
    } catch (e) {
        return [];
    }
};
```

**Propósito**: 
- Parsear el campo JSON `replies` de la base de datos
- Manejar tanto strings JSON como objetos ya parseados
- Retornar array vacío en caso de error

## 🎨 Diseño del Contenido Expandido

### Sección 1: Comentario Completo
```vue
<div class="mb-4">
    <h4 class="text-lg font-semibold mb-2 text-900">
        Comentario Completo
    </h4>
    <div class="surface-50 border-round p-3">
        <p class="text-900 line-height-3 m-0" v-html="data.text"></p>
    </div>
</div>
```

**Estilos aplicados**:
- `surface-50`: Fondo gris claro
- `border-round`: Bordes redondeados
- `p-3`: Padding de 3 unidades
- `v-html`: Renderiza HTML del comentario (negritas, cursivas, links, etc.)

### Sección 2: Lista de Respuestas
```vue
<div v-for="(reply, index) in parseReplies(data.replies)" 
     :key="reply.id"
     class="flex gap-3 mb-3 p-3 surface-50 border-round"
>
    <Avatar :image="reply.author_image" size="large" />
    
    <div class="flex-1">
        <!-- Header con autor y fecha -->
        <div class="flex justify-content-between align-items-start mb-2">
            <div>
                <div class="font-semibold text-900">{{ reply.author }}</div>
                <small class="text-500">{{ formatDate(reply.published_at) }}</small>
            </div>
            
            <!-- Tag de likes -->
            <Tag v-if="reply.like_count > 0" 
                 :value="reply.like_count" 
                 severity="success" 
                 rounded
            >
                <i class="pi pi-thumbs-up mr-1"></i>
                {{ reply.like_count }}
            </Tag>
        </div>
        
        <!-- Texto de la respuesta -->
        <div class="text-900" v-html="reply.text"></div>
    </div>
</div>
```

### Sección 3: Estados Vacíos

**Si no hay respuestas**:
```vue
<div v-else class="text-center text-500 py-4">
    <i class="pi pi-comments text-3xl mb-2"></i>
    <p class="m-0">Este comentario no tiene respuestas</p>
</div>
```

**Si replies está vacío pero reply_count > 0**:
```vue
<div v-if="parseReplies(data.replies).length === 0" 
     class="text-center text-500 py-3"
>
    No hay respuestas para mostrar
</div>
```

## 🎭 Interacción del Usuario

### 1. Expandir Comentario
```
Usuario hace clic en el chevron → 
Fila se expande → 
Se muestra comentario completo + respuestas
```

### 2. Colapsar Comentario
```
Usuario hace clic en el chevron nuevamente → 
Fila se colapsa → 
Vuelve al estado normal
```

### 3. Múltiples Expansiones
```
Usuario puede expandir varios comentarios simultáneamente →
expandedRows = [1, 5, 8] →
Todas las filas expandidas se muestran a la vez
```

## 📊 Estructura de Datos

### Comentario Principal
```javascript
{
    id: 1,
    author: "@Usuario",
    author_image: "https://...",
    text: "<p>Comentario con <b>HTML</b></p>",
    text_original: "Comentario sin HTML",
    like_count: 10,
    reply_count: 3,
    replies: '[{"id":"1","author":"..."}]',  // JSON string
    published_at: "2025-10-08T12:00:00Z"
}
```

### Respuestas (después de parsear)
```javascript
[
    {
        id: "reply_1",
        author: "@Respuesta1",
        author_image: "https://...",
        text: "Texto de respuesta",
        like_count: 5,
        published_at: "2025-10-08T13:00:00Z"
    },
    {
        id: "reply_2",
        author: "@Respuesta2",
        // ...
    }
]
```

## 🎨 Clases CSS Utilizadas

### PrimeVue / PrimeFlex
- `surface-50`: Fondo gris muy claro
- `border-round`: Bordes redondeados (border-radius)
- `p-3`: Padding de 1.5rem
- `mb-2`, `mb-3`, `mb-4`: Margin bottom
- `gap-3`: Gap de flex/grid de 1.5rem
- `text-900`: Color de texto oscuro
- `text-500`: Color de texto gris
- `flex`, `flex-1`: Flexbox utilities
- `justify-content-between`: Space between
- `align-items-start`: Align items al inicio

### Custom
```css
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

## 🔄 Flujo Completo

### 1. Usuario ve la tabla
```
┌─────────────────────────────────────────┐
│ [>] Autor    Comentario    Likes  Rep  │
│ [>] User1    Texto...      10     3    │
│ [>] User2    Texto...      5      0    │
└─────────────────────────────────────────┘
```

### 2. Usuario expande fila 1
```
┌─────────────────────────────────────────┐
│ [v] User1    Texto...      10     3    │
│                                         │
│     ┌─ Comentario Completo ─────────┐  │
│     │ Texto completo del comentario │  │
│     └──────────────────────────────┘  │
│                                         │
│     ┌─ Respuestas (3) ──────────────┐  │
│     │ [👤] User2: Respuesta 1      │  │
│     │ [👤] User3: Respuesta 2      │  │
│     │ [👤] User4: Respuesta 3      │  │
│     └──────────────────────────────┘  │
│                                         │
│ [>] User2    Texto...      5      0    │
└─────────────────────────────────────────┘
```

## 🚀 Ventajas Implementadas

### ✅ UX Mejorada
- **No interrumpe el flujo**: El usuario permanece en la tabla
- **Contexto visual**: Mantiene visible otros comentarios
- **Expansión múltiple**: Puede comparar varios comentarios a la vez
- **Scroll natural**: No hay saltos de navegación

### ✅ Performance
- **Carga bajo demanda**: Las respuestas se parsean solo al expandir
- **Menos DOM**: No mantiene modales ocultos en memoria
- **Re-render eficiente**: Solo actualiza la fila expandida

### ✅ Accesibilidad
- **Keyboard navigation**: Funciona con teclado (Enter/Space)
- **Screen readers**: Correctamente anunciado
- **Estados claros**: Expandido/colapsado visualmente obvio

## 📝 Cambios Realizados

### Eliminado
❌ `Dialog` component y su import
❌ `showDialog` ref
❌ `selectedComment` ref
❌ `viewComment()` function
❌ Botón "Ver detalles" en acciones

### Agregado
✅ `expandedRows` ref
✅ `parseReplies()` function
✅ `Column :expander="true"`
✅ `template #expansion`
✅ `v-model:expandedRows` en DataTable

## 🧪 Testing

### Casos de Prueba

1. **Expandir comentario sin respuestas**
   - ✅ Muestra "Este comentario no tiene respuestas"

2. **Expandir comentario con respuestas**
   - ✅ Muestra todas las respuestas con avatares
   - ✅ Formatea fechas correctamente
   - ✅ Muestra likes de cada respuesta

3. **Expandir múltiples comentarios**
   - ✅ Permite expandir varios a la vez
   - ✅ Mantiene estado de cada uno

4. **Colapsar comentario**
   - ✅ Oculta el contenido expandido
   - ✅ Vuelve a estado normal

5. **Paginación con expansión**
   - ✅ Al cambiar de página, resetea expansiones
   - ✅ No mantiene estado entre páginas

## 🎯 Resultado Final

Una experiencia fluida donde:
- Usuario hace clic en `>` para expandir
- Ve el comentario completo con formato HTML
- Ve todas las respuestas con avatares y likes
- Puede expandir múltiples comentarios para comparar
- Hace clic en `v` para colapsar
- No necesita abrir/cerrar modales

**¡Row Expansion es la opción perfecta para este caso de uso!** 🎉
