# Resumen Rápido: Archivos para Web Hosting

## 🎯 Respuesta Directa

**Para subir al hosting, ejecuta:**
```batch
generar_release.bat
```

**Luego sube TODO el contenido de la carpeta:**
```
C:\Users\TeVsKo\Desktop\SpaceParkMaster\release_web\
```

---

## ✅ Archivos que SE SUBEN (Incluidos en release_web)

### Archivos Principales
- ✅ `.htaccess` - Configuración Apache
- ✅ `index.php`, `login.php`, `signup.php`, etc.
- ✅ `composer.json` - Configuración de dependencias

### Carpetas Completas
- ✅ `admin/` - Panel de administración
- ✅ `api/` - Endpoints API
- ✅ `assets/` - CSS, JS, imágenes
- ✅ `config/` - Configuración
- ✅ `install/` - Instalador web
- ✅ `migrations/` - Migraciones SQL
- ✅ `pos/` - Sistema POS
- ✅ `src/` - Clases PHP (Database, Auth, Mailer, etc.)
- ✅ **`vendor/`** - **PHPMailer** (¡IMPORTANTE!)
- ✅ `logs/` - Carpeta de logs (vacía)

---

## ❌ Archivos que NO se suben (Excluidos automáticamente)

### Documentación
- ❌ `*.md` (README, guías)
- ❌ `*.txt` (excepto robots.txt)

### Scripts de Desarrollo
- ❌ `*.bat` (build.bat, generar_release.bat)
- ❌ `*.ps1` (prepare_release.ps1)
- ❌ `scripts/` (carpeta completa)

### Empaquetado
- ❌ `packaging/` - Herramientas Windows
- ❌ `out/`, `build/` - Salidas de compilación
- ❌ `.git/`, `.gitignore` - Control de versiones

### Datos Locales
- ❌ `data/` - SQLite local (solo para clientes Windows)
- ❌ `*.sqlite` - Bases de datos locales
- ❌ `phpdesktop/` - Runtime Windows

### Testing
- ❌ `debug_*.php`, `test_*.php`
- ❌ `tests/`

---

## 📦 Tamaño Total

**~1.7 MB** (muy ligero, funciona en cualquier hosting)

---

## 🚀 Proceso Completo (3 pasos)

### 1️⃣ Generar Release
```batch
cd C:\Users\TeVsKo\Desktop\SpaceParkMaster
generar_release.bat
```

### 2️⃣ Subir vía FTP
- Conectar a tu hosting
- Ir a `/public_html/` o `/web/`
- Subir TODO de `release_web/`
- ⚠️ Verificar que `.htaccess` y `vendor/` se subieron

### 3️⃣ Instalar
- Crear base de datos MySQL en el panel del hosting
- Ir a `https://tudominio.com/install/`
- Completar formulario
- ¡Listo!

---

## ⚠️ IMPORTANTE

### Verificar que se incluya `vendor/`

Después de ejecutar `generar_release.bat`, verificar:
```
release_web/
└── vendor/
    ├── autoload.php
    └── phpmailer/
        └── phpmailer/
            └── src/
```

Si `vendor/` NO está en `release_web/`, ejecutar:
```batch
.\install_composer.bat
.\generar_release.bat
```

---

## 📖 Guía Completa

Para instrucciones detalladas, ver:
- `deployment_guide.md` - Guía completa de despliegue
- `GUIA_DESPLIEGUE_CDMON.md` - Guía específica para CDMON
