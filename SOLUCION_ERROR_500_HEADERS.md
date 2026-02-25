# Solución: Error 500 - CGI Headers Malformed

## 🔴 Error

```
Error 500: Internal Server Error
CGI program sent malformed or too big (>16384 bytes) HTTP headers: []
```

![Error Screenshot](C:/Users/TeVsKo/.gemini/antigravity/brain/c7323986-16ed-4b11-bdd7-19e8098b59d8/uploaded_media_1770251998933.jpg)

## 🔍 Causa

PHP está enviando demasiada información en los HTTP headers, probablemente debido a:
- Errores o warnings de PHP que se muestran antes del contenido
- `display_errors = On` en php.ini
- Falta de output buffering

## ✅ Solución Automática

Ejecuta este script:

```powershell
cd C:\Users\TeVsKo\Desktop\SpaceParkMaster\packaging
.\fix_php_headers.ps1
```

**¿Qué hace?**
1. ✅ Desactiva `display_errors` (evita errores en headers)
2. ✅ Activa `log_errors` (guarda errores en archivo)
3. ✅ Configura `output_buffering` (evita headers grandes)
4. ✅ Crea backup automático de php.ini

## 🔧 Solución Manual

Si prefieres hacerlo manualmente:

1. **Abrir php.ini:**
   ```
   C:\phpdesktop-chrome-130.1-php-8.3\php.ini
   ```

2. **Buscar y cambiar estas líneas:**

   ```ini
   ; Desactivar errores en pantalla
   display_errors = Off
   display_startup_errors = Off
   
   ; Activar log de errores
   log_errors = On
   error_log = php_errors.log
   
   ; Configurar buffering
   output_buffering = 4096
   implicit_flush = Off
   
   ; Reportar solo errores críticos
   error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
   ```

3. **Guardar y cerrar**

4. **Recompilar instalador:**
   ```batch
   cd C:\Users\TeVsKo\Desktop\SpaceParkMaster
   .\build.bat
   ```

## 📋 Verificación

Después de aplicar la solución:

1. ✅ Recompilar instalador
2. ✅ Instalar en PC de prueba
3. ✅ Abrir SpacePark POS
4. ✅ Verificar que no aparezca el error 500

## 🆘 Si el Error Persiste

Si después de aplicar la solución el error continúa:

### Opción 1: Verificar errores de PHP

Los errores ahora se guardan en archivo. Buscar:
```
C:\Program Files\SpacePark\php_errors.log
```

### Opción 2: Verificar permisos

Asegurarse de que la carpeta de instalación tenga permisos de escritura.

### Opción 3: Verificar extensiones PHP

Algunas extensiones pueden causar problemas. Editar `php.ini` y comentar extensiones no necesarias:

```ini
;extension=php_curl.dll
;extension=php_gd2.dll
```

## 📊 Configuraciones Aplicadas

| Configuración | Valor | Propósito |
|---------------|-------|-----------|
| `display_errors` | Off | Evita errores en headers |
| `log_errors` | On | Guarda errores en archivo |
| `output_buffering` | 4096 | Buffer de salida |
| `error_reporting` | E_ALL & ~E_DEPRECATED | Solo errores críticos |

## ✅ Resultado Esperado

Después de aplicar la solución:
- ✅ SpacePark POS abre correctamente
- ✅ No aparece error 500
- ✅ Errores se guardan en archivo de log
- ✅ Interfaz funciona normalmente
