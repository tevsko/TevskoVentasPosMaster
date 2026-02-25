# Instrucciones para Cambiar el Icono a Astronauta

## 🚀 Icono Generado

He creado un icono de astronauta profesional para SpacePark POS.

![Icono Astronauta](C:/Users/TeVsKo/.gemini/antigravity/brain/c7323986-16ed-4b11-bdd7-19e8098b59d8/astronaut_icon_1770250277554.png)

## 📋 Pasos para Implementar

### Opción 1: Usar Convertidor Online (Más Rápido)

1. **Convertir PNG a ICO:**
   - Ir a: https://convertio.co/es/png-ico/
   - Subir: `C:\Users\TeVsKo\.gemini\antigravity\brain\c7323986-16ed-4b11-bdd7-19e8098b59d8\astronaut_icon_1770250277554.png`
   - Descargar el archivo `.ico` generado

2. **Copiar el ICO a PHPDesktop:**
   ```batch
   copy astronaut_icon.ico C:\phpdesktop-chrome-130.1-php-8.3\spacepark.ico
   ```

3. **Actualizar `settings.json` de PHPDesktop:**
   
   Editar: `C:\phpdesktop-chrome-130.1-php-8.3\settings.json`
   
   Buscar la línea `"icon"` y cambiarla a:
   ```json
   "icon": "spacepark.ico"
   ```

4. **Recompilar el instalador:**
   ```batch
   cd C:\Users\TeVsKo\Desktop\SpaceParkMaster
   .\build.bat
   ```

---

### Opción 2: Usar ImageMagick (Si está instalado)

Si tienes ImageMagick instalado:

```powershell
magick convert astronaut_icon_1770250277554.png -define icon:auto-resize=256,128,64,48,32,16 spacepark.ico
```

---

### Opción 3: Usar PowerShell (Conversión Básica)

Crear un script PowerShell para convertir:

```powershell
# convert_to_ico.ps1
Add-Type -AssemblyName System.Drawing

$pngPath = "C:\Users\TeVsKo\.gemini\antigravity\brain\c7323986-16ed-4b11-bdd7-19e8098b59d8\astronaut_icon_1770250277554.png"
$icoPath = "C:\phpdesktop-chrome-130.1-php-8.3\spacepark.ico"

$img = [System.Drawing.Image]::FromFile($pngPath)
$icon = [System.Drawing.Icon]::FromHandle($img.GetHicon())
$stream = [System.IO.File]::Create($icoPath)
$icon.Save($stream)
$stream.Close()
$img.Dispose()

Write-Host "Icono creado: $icoPath"
```

---

## 🔧 Configuración del Instalador

Una vez que tengas el archivo `spacepark.ico` en PHPDesktop, el instalador lo incluirá automáticamente.

### Verificar que el icono se use en el acceso directo

Editar `packaging\SpaceParkInstaller.iss`:

```ini
[Icons]
Name: "{group}\SpacePark Pos Ventas"; Filename: "{app}\phpdesktop-chrome.exe"; WorkingDir: "{app}"; IconFilename: "{app}\spacepark.ico"
Name: "{commondesktop}\SpacePark Pos Ventas"; Filename: "{app}\phpdesktop-chrome.exe"; Tasks: desktopicon; IconFilename: "{app}\spacepark.ico"
```

---

## ✅ Resultado Final

Después de recompilar, el instalador:
- ✅ Usará el icono del astronauta en el escritorio
- ✅ Usará el icono del astronauta en el menú inicio
- ✅ Se verá profesional y acorde al nombre "SpacePark"

---

## 🆘 Si Necesitas Ayuda

Si no tienes un convertidor de PNG a ICO, puedo:
1. Proporcionarte un script automatizado
2. Darte un enlace directo a un convertidor online
3. Crear el archivo ICO directamente si tienes las herramientas necesarias
