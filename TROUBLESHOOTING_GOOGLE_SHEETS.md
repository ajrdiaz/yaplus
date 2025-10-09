# 🔧 Troubleshooting - Google Sheets Integration

## ❌ Error: "Google Sheets API no está configurada"

### Causa Común #1: Archivo de credenciales mal nombrado
**Síntoma:** El archivo existe pero el servicio no lo encuentra

**Solución:**
```bash
# Verificar el nombre del archivo
ls -la storage/app/google*

# Si el archivo se llama google-credentials.json.json (doble extensión)
mv storage/app/google-credentials.json.json storage/app/google-credentials.json

# Limpiar caché
php artisan config:clear
php artisan cache:clear
```

### Causa Común #2: Archivo no existe
**Síntoma:** No se encuentra el archivo

**Solución:**
1. Descargar el JSON de credenciales desde Google Cloud Console
2. Colocar en: `storage/app/google-credentials.json`
3. Verificar permisos: `chmod 644 storage/app/google-credentials.json`

### Causa Común #3: JSON inválido
**Síntoma:** Archivo existe pero está corrupto

**Solución:**
```bash
# Verificar si el JSON es válido
php -r "json_decode(file_get_contents('storage/app/google-credentials.json')); echo (json_last_error() === JSON_ERROR_NONE) ? 'JSON válido' : 'JSON inválido: ' . json_last_error_msg();"

# Si es inválido, descarga nuevamente el archivo desde Google Cloud Console
```

---

## ❌ Error: "No se puede acceder a la hoja"

### Causa: Falta compartir la hoja con la cuenta de servicio

**Solución:**
1. Abre el archivo `storage/app/google-credentials.json`
2. Busca el campo `client_email` (ejemplo: `sheets-reader@proyecto.iam.gserviceaccount.com`)
3. Abre tu Google Sheet
4. Click en "Compartir" (Share)
5. Pega el email de la cuenta de servicio
6. Asigna permisos de **"Viewer"** (solo lectura)
7. Envía la invitación
8. Intenta importar de nuevo

---

## ❌ Error: "No se encontraron respuestas"

### Causa #1: Hoja vacía
**Solución:** Verifica que el formulario tenga respuestas enviadas

### Causa #2: Nombre de hoja incorrecto
**Solución:** ✅ **RESUELTO AUTOMÁTICAMENTE**

El sistema ahora detecta automáticamente el nombre de la primera hoja, ya sea:
- "Form Responses 1" (inglés)
- "Respuestas de formulario 1" (español)
- Cualquier otro idioma

No necesitas hacer nada, funcionará con cualquier nombre de hoja.

### Causa #3: Formato incorrecto
**Solución:** La primera fila debe ser los encabezados (nombres de preguntas)

---

## ✅ Verificar que todo funcione

### Paso 1: Probar el servicio
```bash
php artisan tinker --execute="use App\Services\GoogleSheetsService; \$service = new GoogleSheetsService(); echo 'Configurado: ' . (\$service->isConfigured() ? 'SI' : 'NO');"
```
Debe decir: **"Configurado: SI"**

### Paso 2: Probar lectura de hoja (sustituye con tu ID)
```bash
php artisan tinker
```
```php
use App\Services\GoogleSheetsService;
$service = new GoogleSheetsService();
$spreadsheetId = '1ABC...XYZ'; // Tu ID de hoja
$info = $service->getSpreadsheetInfo($spreadsheetId);
print_r($info);
```

### Paso 3: Leer respuestas
```php
$data = $service->readSheet($spreadsheetId, 'Form Responses 1!A:Z');
print_r($data);
```

---

## 📋 Checklist de Configuración

- [ ] Proyecto creado en Google Cloud Console
- [ ] Google Sheets API habilitada
- [ ] Cuenta de servicio creada
- [ ] JSON de credenciales descargado
- [ ] Archivo colocado en `storage/app/google-credentials.json`
- [ ] Nombre del archivo es correcto (sin `.json.json`)
- [ ] JSON es válido
- [ ] Hoja compartida con email de cuenta de servicio
- [ ] Permisos de "Viewer" asignados
- [ ] Caché de Laravel limpiada
- [ ] Servicio verificado con tinker

---

## 🔍 Ver Logs

```bash
# Últimas 50 líneas del log
tail -n 50 storage/logs/laravel.log

# Buscar errores de Google Sheets
grep "Google Sheets" storage/logs/laravel.log

# Ver en tiempo real
tail -f storage/logs/laravel.log
```

---

## 🆘 Si nada funciona

1. **Elimina y recrea las credenciales:**
   ```bash
   rm storage/app/google-credentials.json
   # Descarga nuevo JSON desde Google Cloud Console
   # Colócalo de nuevo
   ```

2. **Verifica la API esté habilitada:**
   - Google Cloud Console → APIs & Services → Library
   - Busca "Google Sheets API"
   - Debe estar **"Enabled"**

3. **Verifica los scopes:**
   El código usa: `https://www.googleapis.com/auth/spreadsheets.readonly`
   Este es el scope correcto para solo lectura.

4. **Revisa permisos del archivo:**
   ```bash
   chmod 644 storage/app/google-credentials.json
   chown www-data:www-data storage/app/google-credentials.json  # En Linux
   ```

5. **Reinstala el paquete:**
   ```bash
   composer remove google/apiclient
   composer require google/apiclient:^2.0
   composer dump-autoload
   ```

---

## 💡 Consejos

- **No compartas el archivo `google-credentials.json`** - contiene claves privadas
- **Usa `.gitignore`** para excluir: `storage/app/google-credentials.json`
- **Usa cuenta de servicio** en lugar de OAuth para aplicaciones server-side
- **Mínimo privilegio**: Solo permisos de "Viewer" (lectura)
- **Una cuenta de servicio** puede acceder a múltiples hojas (comparte cada una)

---

## 📞 Obtener el ID de una hoja

De una URL como:
```
https://docs.google.com/spreadsheets/d/1ABC123XYZ456/edit#gid=0
```

El ID es: **`1ABC123XYZ456`**

Lo encuentras entre `/d/` y `/edit`
