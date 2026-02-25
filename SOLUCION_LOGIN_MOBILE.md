# 🔧 Solución: Error de Login en PWA Mobile

## 🎯 Problema

Al intentar hacer login con `empleado1` / `123456`, aparece:
```
Error de conexión. Verifica tu internet.
```

## 🔍 Diagnóstico

El error **NO es de conexión**, sino que:

### Causa 1: No hay datos en la base de datos ✅ (Más Probable)
La migración creó las tablas vacías. No existen:
- Locales de arcade
- Productos  
- **Empleados** (por eso falla el login)

### Causa 2: APIs no están en el servidor
Los archivos `api/mobile/*.php` están solo en local, no en tevsko.com.ar

### Causa 3: Error de CORS o PHP
El servidor podría estar bloqueando las peticiones o hay un error PHP.

---

## ✅ Solución Paso a Paso

### Paso 1: Verificar qué Causa es

**Abrir DevTools del navegador** (F12):
1. Ir a la pestaña **Network**
2. Intentar login nuevamente
3. Buscar la petición a `auth.php`
4. Ver el **Status Code**:
   - `404 Not Found` → Las APIs no están en el servidor
   - `500 Internal Server Error` → Error PHP
   - `200 OK` → Ver la respuesta JSON

**Resultado esperado**:
```json
{
  "error": "Credenciales inválidas"
}
```

---

### Paso 2: Insertar Datos de Prueba

**Archivo**: `migrations/022_arcade_test_data.sql`

**Instrucciones**:
1. **Abrir phpMyAdmin**
2. **Seleccionar tu base de datos**
3. **Ir a pestaña "SQL"**
4. **Copiar todo el contenido** de `022_arcade_test_data.sql`
5. **IMPORTANTE**: Cambiar la línea:
   ```sql
   SET @tenant_id = 1; -- CAMBIAR ESTE VALOR
   ```
   Por tu tenant_id real (probablemente 1, pero verificar)

6. **Ejecutar**

**Resultado esperado**:
```
Datos insertados correctamente
location_id: 1
productos: 3
empleados: 1
```

---

### Paso 3: Verificar Datos Insertados

**Ejecutar en phpMyAdmin**:
```sql
-- Ver el empleado creado
SELECT * FROM arcade_employees;

-- Debería mostrar:
-- id: 1
-- username: empleado1
-- full_name: Juan Pérez
-- daily_salary: 20000.00
```

---

### Paso 4: Subir APIs al Servidor (Si no están)

Si las APIs no están en tevsko.com.ar, subirlas:

**Archivos a subir**:
```
api/mobile/auth.php
api/mobile/get_products.php
api/mobile/submit_report.php
api/mobile/get_reports.php
```

**Crear carpeta**:
```
assets/uploads/arcade/photos/ (permisos 755)
```

---

### Paso 5: Testear Login

**Credenciales**:
- Usuario: `empleado1`
- Contraseña: `123456`

**Resultado esperado**:
- ✅ Login exitoso
- ✅ Redirige a `/mobile/report.html`
- ✅ Muestra "Arcade Central" en el header
- ✅ Muestra "Juan Pérez" como empleado

---

## 🔐 Sobre tu Pregunta de Seguridad

> "¿No debería poder usar la contraseña de empleado que tiene el cliente?"

**Respuesta**: No, y está bien así por seguridad.

### Diferencia entre Usuarios

| Tipo | Tabla | Uso | Acceso |
|------|-------|-----|--------|
| **Usuarios Admin/POS** | `users` | Panel admin, POS desktop | Computadoras |
| **Empleados Móviles** | `arcade_employees` | PWA móvil | Celulares |

### ¿Por qué son separados?

1. **Seguridad**: Los empleados móviles solo necesitan acceso limitado
2. **Simplicidad**: No necesitan permisos complejos
3. **Control**: El dueño crea usuarios móviles específicos
4. **Auditoría**: Saber quién reportó desde móvil

### Flujo Correcto

1. **Dueño** entra al panel admin (usuario normal)
2. **Dueño** crea empleados móviles en `admin/arcade_employees.php` (Fase 4)
3. **Empleado** usa esas credenciales en la PWA

---

## 📊 Resumen

**Problema**: No hay datos en la base de datos  
**Solución**: Ejecutar `022_arcade_test_data.sql`  
**Credenciales**: `empleado1` / `123456`

---

## ⏭️ Después de Resolver

Una vez que funcione el login:
1. ✅ Testear formulario de reporte
2. ✅ Verificar cálculos automáticos
3. ✅ Probar envío de reporte
4. 🚀 Continuar con Fase 3 (Offline)

---

**¿Ejecutaste el script de datos de prueba? ¿Qué resultado obtuviste?**
