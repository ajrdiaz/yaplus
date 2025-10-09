# 📋 Guía de Uso: Google Forms - Investigación de Buyer Persona

## 🎯 Propósito
Esta herramienta te permite analizar ## 📋 Formato Esperado del Google Sheet

La hoja debe tener esta estructura:

| Marca temporal | Pregunta 1 | Pregunta 2 | Pregunta 3 | Correo electrónico |
|----------------|------------|------------|------------|--------------------|
| 28/09/2025 1:30:48 | Respuesta 1 | Respuesta 2 | Respuesta 3 | email@example.com |
| 29/09/2025 2:45:12 | Respuesta 1 | Respuesta 2 | Respuesta 3 | otro@example.com |

**Importante:**
- La primera fila debe ser los encabezados (nombres de preguntas)
- ✅ El sistema detecta automáticamente el nombre de la hoja (funciona en cualquier idioma)
- ✅ Soporta múltiples formatos de fecha:
  - Español: `28/09/2025 1:30:48` (día/mes/año)
  - Inglés: `9/28/2025 1:30:48` (mes/día/año)
  - ISO: `2025-09-28 01:30:48`
- Marca temporal y Email son opcionales pero recomendados Google Forms con **IA (ChatGPT)** para investigación de **buyer persona interna**.

Complementa la investigación externa de YouTube con feedback directo de tu audiencia.

---

## 📝 Requisitos Previos

### 1. Configurar Credenciales de Google API
**Solo la primera vez**, sigue estas instrucciones:
👉 Ver archivo: `GOOGLE_SHEETS_SETUP.md`

**Resumen rápido:**
- Crear proyecto en Google Cloud Console
- Habilitar Google Sheets API
- Crear cuenta de servicio
- Descargar JSON de credenciales
- Colocar en `storage/app/google-credentials.json`

### 2. Preparar tu Formulario de Google
1. Crea un formulario en [Google Forms](https://forms.google.com)
2. Google automáticamente crea una hoja de cálculo vinculada (View Responses → Sheets)
3. **Comparte la hoja** con el email de la cuenta de servicio (permisos de "Viewer")

---

## 🚀 Cómo Usar la Aplicación

### Paso 1: Importar Respuestas

1. Ve a la sección **"Google Forms"** en el menú lateral
2. Completa el formulario de importación:

   **Campos requeridos:**
   - **URL de Google Sheets**: Pega la URL completa de la hoja donde están las respuestas
     - Ejemplo: `https://docs.google.com/spreadsheets/d/1ABC...XYZ/edit`
   - **Título del Formulario**: Un nombre descriptivo
     - Ejemplo: "Encuesta Buyer Persona - Mayo 2024"

   **Campos opcionales (pero recomendados):**
   - **Descripción**: Propósito de la encuesta
   - **Contexto de Negocio** (expande la sección):
     - **Nombre del Producto**: El producto que estás investigando
     - **Audiencia Objetivo**: Tu público meta
     - **Descripción del Producto**: Detalles adicionales
     - **Objetivo de Investigación**: Qué buscas descubrir
     - **Contexto Adicional**: Info relevante

3. Haz clic en **"Importar Respuestas"**

**¿Qué hace esto?**
- Lee la hoja de Google Sheets
- Extrae todas las respuestas (una fila = una respuesta)
- Las guarda en la base de datos
- Combina todas las respuestas de cada participante en un texto

---

### Paso 2: Analizar con IA

*(Próximamente - en desarrollo)*

1. Selecciona un formulario de la lista
2. Haz clic en **"Analizar con IA"**
3. La IA categorizará las respuestas en:
   - 🆘 **Necesidad**: Qué necesita tu audiencia
   - 😓 **Dolor**: Problemas o frustraciones
   - ✨ **Sueño**: Aspiraciones y deseos
   - 🚧 **Objeción**: Barreras para comprar
   - ❓ **Pregunta**: Dudas frecuentes
   - 👍 **Experiencia positiva**: Qué les gustó
   - 👎 **Experiencia negativa**: Qué les disgustó
   - 💡 **Sugerencia**: Ideas de mejora

---

### Paso 3: Ver Resultados

*(Próximamente - en desarrollo)*

- Navega por las categorías
- Analiza sentimientos (positivo, neutral, negativo)
- Revisa palabras clave más frecuentes
- Lee insights generados por IA

---

## 💡 Consejos para Mejores Resultados

### Diseña Buenas Preguntas
✅ **BIEN:**
- "¿Qué problemas enfrentas al [hacer X]?"
- "¿Qué te impide [lograr Y]?"
- "Si pudieras mejorar algo de [producto], ¿qué sería?"

❌ **EVITAR:**
- Preguntas Sí/No simples
- Preguntas de opción múltiple sin campo abierto
- Preguntas muy genéricas

### Usa el Contexto de Negocio
⚡ **Mejora la calidad del análisis:**
- **Con contexto**: IA entiende tu producto y audiencia → análisis preciso
- **Sin contexto**: IA da análisis genérico → menos valor

**Ejemplo con contexto:**
```
Producto: Curso de Marketing Digital
Audiencia: Emprendedores 25-40 años
Objetivo: Identificar objeciones de precio
```
→ IA detecta: "No puedo pagarlo ahora" como **objeción financiera**

**Sin contexto:**
→ IA detecta: "No puedo pagarlo ahora" como **comentario general**

---

## 📊 Formato Esperado del Google Sheet

La hoja debe tener esta estructura:

| Marca temporal | Pregunta 1 | Pregunta 2 | Pregunta 3 | Correo electrónico |
|----------------|------------|------------|------------|--------------------|
| 2024-05-01 10:30 | Respuesta 1 | Respuesta 2 | Respuesta 3 | email@example.com |
| 2024-05-01 11:45 | Respuesta 1 | Respuesta 2 | Respuesta 3 | otro@example.com |

**Importante:**
- La primera fila debe ser los encabezados (nombres de preguntas)
- ✅ El sistema detecta automáticamente el nombre de la hoja (funciona en cualquier idioma)
- Marca temporal y Email son opcionales pero recomendados

---

## 🔄 Actualizaciones y Reimportación

### ¿Cómo actualizar si hay nuevas respuestas?
1. Ve al formulario en la lista
2. Haz clic en **"Reimportar"** (próximamente)
3. Solo se agregarán las respuestas nuevas (no duplicados)

### ¿Puedo editar el contexto después?
✅ **SÍ:**
1. Haz clic en el ícono de **briefcase** (maletín) en la tabla
2. Actualiza los campos que necesites
3. Guarda cambios
4. El análisis futuro usará el nuevo contexto

---

## ⚠️ Solución de Problemas

### Error: "No se puede acceder a la hoja"
**Causa:** La cuenta de servicio no tiene permisos
**Solución:**
1. Abre la Google Sheet
2. Click en "Share"
3. Pega el email de la cuenta de servicio (está en el JSON)
4. Dale permisos de "Viewer"
5. Intenta de nuevo

### Error: "No se encontraron respuestas"
**Causa:** La hoja está vacía o tiene nombre incorrecto
**Solución:**
- Verifica que haya respuestas en el formulario
- Verifica que la hoja se llame "Form Responses 1"
- Si cambiaste el nombre, vuelve al nombre original

### Error: "Credenciales inválidas"
**Causa:** El archivo `google-credentials.json` no está configurado
**Solución:**
- Sigue la guía completa en `GOOGLE_SHEETS_SETUP.md`
- Verifica que el archivo esté en `storage/app/google-credentials.json`
- Verifica que el JSON sea válido (usa un validador JSON online)

---

## 🎓 Casos de Uso Reales

### 1. Validación de Producto
**Objetivo:** ¿Mi curso resuelve los problemas correctos?
**Preguntas:**
- ¿Cuál es tu mayor reto con [tema]?
- ¿Qué has intentado antes?
- ¿Qué esperas lograr en 3 meses?

### 2. Objeciones de Venta
**Objetivo:** ¿Por qué no compran?
**Preguntas:**
- ¿Qué te impide comprar ahora?
- ¿Qué información necesitas para decidir?
- ¿Qué cambiarías del precio/oferta?

### 3. Mejora de Producto
**Objetivo:** ¿Cómo mejorar?
**Preguntas:**
- ¿Qué te ha gustado más?
- ¿Qué mejorarías?
- ¿Qué falta?

---

## 📈 Combinando YouTube + Google Forms

### Investigación 360°

**YouTube (Investigación Externa):**
- Comentarios de competidores
- Opiniones no filtradas
- Tendencias del mercado
- Lenguaje natural de la audiencia

**Google Forms (Investigación Interna):**
- Feedback directo de tu audiencia
- Preguntas específicas
- Validación de hipótesis
- Datos estructurados

**Combina ambos para:**
1. Descubrir problemas (YouTube) → Validar con tu audiencia (Forms)
2. Ver quejas de competidores (YouTube) → Preguntar si tu audiencia las tiene (Forms)
3. Identificar lenguaje (YouTube) → Confirmar resonancia (Forms)

---

## 🔐 Seguridad y Privacidad

### Datos que se guardan:
- ✅ Respuestas del formulario
- ✅ Email del respondente (si está en la hoja)
- ✅ Fecha de respuesta
- ✅ Análisis de IA

### Datos que NO se guardan:
- ❌ Credenciales de Google
- ❌ Tokens de acceso
- ❌ Información personal más allá de lo en la hoja

### Recomendaciones:
- No compartas las credenciales JSON
- Usa anonimización si recolectas datos sensibles
- Cumple con GDPR/leyes locales al hacer encuestas

---

## ❓ Preguntas Frecuentes

**P: ¿Cuántas respuestas puedo importar?**
R: No hay límite técnico, pero se recomienda analizar en lotes de 100-500 respuestas para mejor rendimiento.

**P: ¿Puedo importar formularios antiguos?**
R: Sí, cualquier formulario que tenga respuestas guardadas en Sheets.

**P: ¿Se cobra por las llamadas a OpenAI?**
R: Sí, se usa tu API Key de OpenAI. Costo aproximado: $0.001 por respuesta (muy bajo).

**P: ¿Puedo exportar los resultados?**
R: Próximamente se agregará exportación a CSV/PDF.

**P: ¿Y si cambio las preguntas del formulario?**
R: Puedes importar la nueva versión como un formulario separado, o reimportar el mismo (se agregarán las nuevas).

---

## 🆘 Soporte

Si tienes problemas:
1. Revisa la sección "Solución de Problemas"
2. Verifica los logs en `storage/logs/laravel.log`
3. Consulta `GOOGLE_SHEETS_SETUP.md` para configuración técnica

---

**¡Listo! Ya puedes empezar a analizar tus encuestas con IA.** 🚀
