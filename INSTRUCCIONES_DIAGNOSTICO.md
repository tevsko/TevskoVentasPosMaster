# 🔍 Instrucciones de Diagnóstico - Error 500

## Problema
Error 500 aparece en PC de prueba pero NO en PC de desarrollo.

## Archivos de Diagnóstico Creados

### 1. test_phpdesktop.php
**Qué hace:** Verifica que PHP y PHPDesktop funcionen correctamente
**Cómo usarlo:**
1. Instala SpacePark en la PC de prueba
2. Abre: `C:\Program Files (x86)\SpacePark\www\test_phpdesktop.php`
3. O edita `settings.json` para que `index_files` incluya `test_phpdesktop.php`

### 2. test_database.php
**Qué hace:** Verifica la conexión a la base de datos
**Cómo usarlo:**
1. Después de que `test_phpdesktop.php` funcione
2. Abre: `http://127.0.0.1:PORT/test_database.php`

## Pasos de Diagnóstico

### Paso 1: Verificar PHPDesktop
```
1. Instalar SpacePark
2. Modificar settings.json:
   - Cambiar "index_files": ["test_phpdesktop.php", "index.html", "index.php"]
3. Abrir SpacePark
4. ¿Qué pasa?
   - ✅ Si ves la página de test → PHP funciona, ir a Paso 2
   - ❌ Si ves Error 500 → Problema de PHPDesktop o permisos
```

### Paso 2: Verificar Base de Datos
```
1. Abrir: http://127.0.0.1:PORT/test_database.php
2. ¿Qué pasa?
   - ✅ Si ves "Conexión exitosa" → Base de datos OK, ir a Paso 3
   - ❌ Si ves error → Problema de base de datos
```

### Paso 3: Verificar index.php
```
1. Restaurar settings.json original
2. Abrir SpacePark normalmente
3. ¿Qué pasa?
   - ✅ Si funciona → ¡Problema resuelto!
   - ❌ Si Error 500 → El problema está en index.php o archivos relacionados
```

## Información a Recopilar

Si el error persiste, necesito:

### A. Resultado de test_phpdesktop.php
- ¿Se ve la página?
- ¿Qué dice en "display_errors"?
- ¿Qué dice en "output_buffering"?

### B. Resultado de test_database.php
- ¿Hay error?
- ¿Qué dice el error exacto?
- ¿Cuántas tablas muestra?

### C. Archivos de log
- `C:\Program Files (x86)\SpacePark\debug.log`
- `C:\Program Files (x86)\SpacePark\php_errors.log` (si existe)

### D. Configuración
- Contenido de `C:\Program Files (x86)\SpacePark\php\php.ini` (líneas de errores)
- Contenido de `C:\Program Files (x86)\SpacePark\settings.json`

## Modificar settings.json para Diagnóstico

Editar: `C:\Program Files (x86)\SpacePark\settings.json`

Cambiar:
```json
"index_files": ["index.html", "index.php"]
```

Por:
```json
"index_files": ["test_phpdesktop.php", "index.html", "index.php"]
```

Esto hará que SpacePark abra primero el archivo de test.

## Posibles Causas del Error 500

### 1. PHPDesktop no funciona
- **Síntoma:** test_phpdesktop.php también da Error 500
- **Solución:** Reinstalar VC++ Redistributable

### 2. Permisos de archivos
- **Síntoma:** test_phpdesktop.php funciona pero no puede escribir
- **Solución:** Ejecutar como administrador

### 3. Base de datos no inicializada
- **Síntoma:** test_database.php da error
- **Solución:** Ejecutar postinstall.bat manualmente

### 4. Archivo específico con error
- **Síntoma:** Tests funcionan pero index.php no
- **Solución:** Revisar index.php línea por línea

## Contacto

Envíame capturas de pantalla de:
1. test_phpdesktop.php
2. test_database.php
3. Cualquier mensaje de error

Con esa información podré identificar el problema exacto.
