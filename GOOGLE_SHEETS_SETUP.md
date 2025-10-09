# 🔑 Configuración de Google Sheets API

Esta guía te ayudará a configurar el acceso a Google Sheets API para importar respuestas de Google Forms.

## 📋 Pasos para Obtener Credenciales

### 1. Crear un Proyecto en Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Clic en "Select a project" → "New Project"
3. Nombre del proyecto: `Buyer Persona Research` (o el que prefieras)
4. Clic en "Create"

### 2. Habilitar Google Sheets API

1. En el menú lateral, ve a **APIs & Services** → **Library**
2. Busca "Google Sheets API"
3. Clic en "Google Sheets API"
4. Clic en "Enable"

### 3. Crear Service Account

1. Ve a **APIs & Services** → **Credentials**
2. Clic en "Create Credentials" → "Service Account"
3. Completa:
   - **Service account name**: `sheets-reader`
   - **Service account ID**: (se genera automáticamente)
   - **Description**: "Leer respuestas de Google Forms"
4. Clic en "Create and Continue"
5. En "Grant this service account access to project":
   - Rol: **Viewer** (solo lectura)
6. Clic en "Done"

### 4. Crear JSON Key

1. En la lista de Service Accounts, encuentra el que acabas de crear
2. Clic en el email del service account
3. Ve a la pestaña "Keys"
4. Clic en "Add Key" → "Create new key"
5. Selecciona **JSON**
6. Clic en "Create"
7. Se descargará automáticamente un archivo JSON

### 5. Configurar el Proyecto Laravel

1. Copia el archivo JSON descargado
2. Renómbralo a `google-credentials.json`
3. Colócalo en: `storage/app/google-credentials.json`

```bash
# Desde la raíz del proyecto
cp /ruta/de/descarga/tu-proyecto-xxxxx.json storage/app/google-credentials.json
```

### 6. Dar Permisos a la Hoja de Cálculo

Para que tu aplicación pueda leer las hojas de cálculo:

1. Abre tu Google Sheet (donde están las respuestas del formulario)
2. Clic en "Share" (Compartir)
3. Copia el **email del service account** del archivo JSON
   - Formato: `algo@tu-proyecto.iam.gserviceaccount.com`
4. Pégalo en "Add people and groups"
5. Rol: **Viewer** (solo lectura es suficiente)
6. Desmarca "Notify people" (no es necesario)
7. Clic en "Share"

### 7. Obtener el ID de la Hoja

Para importar las respuestas, necesitas la URL o ID de la hoja:

**Formato de URL:**
```
https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/edit
```

**Ejemplo:**
```
https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
```

El **SPREADSHEET_ID** sería: `1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms`

## ✅ Verificar Configuración

Para verificar que todo funciona:

1. Asegúrate de que el archivo `storage/app/google-credentials.json` existe
2. El service account tiene acceso a la hoja (compartida con el email)
3. La hoja tiene el formato correcto (ver abajo)

## 📊 Formato Esperado de la Hoja

El sistema espera que Google Forms guarde las respuestas en una pestaña llamada **"Form Responses 1"** con este formato:

| Marca temporal | Pregunta 1 | Pregunta 2 | Pregunta 3 | Email |
|---------------|------------|------------|------------|-------|
| 1/10/2025 14:30 | Respuesta 1 | Respuesta 2 | Respuesta 3 | user@email.com |
| 1/10/2025 15:45 | Respuesta 1 | Respuesta 2 | Respuesta 3 | otro@email.com |

**Notas:**
- Primera fila: Headers (nombres de preguntas)
- Primera columna: Timestamp (fecha/hora de respuesta)
- Última columna (opcional): Email del respondente
- Columnas intermedias: Respuestas a las preguntas

## 🔒 Seguridad

⚠️ **IMPORTANTE**: El archivo `google-credentials.json` contiene información sensible.

Asegúrate de que:
- ✅ Está en `.gitignore` (ya incluido)
- ✅ NO lo subas a GitHub
- ✅ Mantén los permisos del service account al mínimo (Viewer)

## 🐛 Solución de Problemas

### Error: "Google Sheets API no está configurada"
- Verifica que el archivo `storage/app/google-credentials.json` existe
- Revisa que el JSON tenga formato válido

### Error: "No se pudo acceder a la hoja de cálculo"
- Verifica que compartiste la hoja con el service account email
- El service account debe tener al menos permisos de **Viewer**

### Error: "No se encontraron respuestas"
- Verifica el nombre de la pestaña (debe ser "Form Responses 1")
- Asegúrate de que hay respuestas en la hoja
- La primera fila debe contener los headers

## 📚 Recursos Adicionales

- [Google Sheets API Documentation](https://developers.google.com/sheets/api)
- [Service Accounts](https://cloud.google.com/iam/docs/service-accounts)
- [OAuth 2.0 Scopes](https://developers.google.com/identity/protocols/oauth2/scopes)

## 🆘 Soporte

Si necesitas ayuda, revisa:
1. Los logs de Laravel: `storage/logs/laravel.log`
2. La configuración del service account
3. Los permisos de la hoja de cálculo
