# Solución: Error VCRUNTIME140.dll No Encontrado

## 🔴 Problema

Al instalar SpacePark POS en una PC limpia (sin Visual C++ Redistributable), aparece el error:

```
La ejecución de código no puede continuar porque no se 
encontró VCRUNTIME140.dll. Este problema se puede 
solucionar reinstalando el programa.
```

## ✅ Soluciones

### Opción 1: Incluir VC++ Redistributable en el Instalador (Recomendado)

Descargar e incluir el instalador de Visual C++ Redistributable en el paquete.

#### Paso 1: Descargar VC++ Redistributable

Descargar desde Microsoft:
- **64-bit:** https://aka.ms/vs/17/release/vc_redist.x64.exe
- **32-bit:** https://aka.ms/vs/17/release/vc_redist.x86.exe

Guardar en: `C:\Users\TeVsKo\Desktop\SpaceParkMaster\packaging\redist\`

#### Paso 2: Modificar `SpaceParkInstaller.iss`

Agregar las siguientes secciones:

```ini
[Files]
; Archivos existentes
Source: "{#SourcePath}\\build\\phpdesktop\\*"; DestDir: "{app}"; Flags: recursesubdirs createallsubdirs ignoreversion

; NUEVO: Incluir VC++ Redistributable
Source: "{#SourcePath}\\redist\\vc_redist.x64.exe"; DestDir: "{tmp}"; Flags: deleteafterinstall

[Run]
; NUEVO: Instalar VC++ Redistributable silenciosamente
Filename: "{tmp}\\vc_redist.x64.exe"; Parameters: "/quiet /norestart"; StatusMsg: "Instalando Visual C++ Runtime..."; Flags: waituntilterminated

; Post-install existente
Filename: "{app}\\postinstall.bat"; Description: "Run post-install tasks"; Flags: runhidden shellexec waituntilterminated
```

---

### Opción 2: Copiar DLLs Directamente (Más Simple)

Copiar las DLLs necesarias directamente en la carpeta de PHPDesktop.

#### Paso 1: Encontrar las DLLs

Las DLLs necesarias están en:
```
C:\Windows\System32\
```

Archivos requeridos:
- `VCRUNTIME140.dll`
- `MSVCP140.dll`
- `VCRUNTIME140_1.dll` (si existe)

#### Paso 2: Copiar a PHPDesktop

Copiar las DLLs a:
```
C:\Users\TeVsKo\Desktop\SpaceParkMaster\packages_wamp\phpdesktop-chrome-57.0-rc-php-7.1.3\
```

Estas DLLs se incluirán automáticamente cuando se ejecute `build.bat`.

---

### Opción 3: Script Automático de Descarga

Crear un script que descargue e instale VC++ Redistributable automáticamente.

#### Crear `packaging/install_vcredist.ps1`

```powershell
# install_vcredist.ps1
$url = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
$output = "$PSScriptRoot\redist\vc_redist.x64.exe"

# Crear carpeta redist si no existe
New-Item -ItemType Directory -Force -Path "$PSScriptRoot\redist" | Out-Null

# Descargar
Write-Host "Descargando Visual C++ Redistributable..."
Invoke-WebRequest -Uri $url -OutFile $output

Write-Host "Descarga completada: $output"
```

#### Ejecutar antes de compilar

```powershell
cd packaging
.\install_vcredist.ps1
```

---

## 🚀 Implementación Recomendada

### Paso a Paso (Opción 2 - Más Simple)

1. **Abrir PowerShell como Administrador**

2. **Copiar DLLs necesarias:**

```powershell
# Navegar a la carpeta de PHPDesktop
cd "C:\Users\TeVsKo\Desktop\SpaceParkMaster\packages_wamp\phpdesktop-chrome-57.0-rc-php-7.1.3"

# Copiar DLLs desde System32
Copy-Item "C:\Windows\System32\VCRUNTIME140.dll" -Destination "."
Copy-Item "C:\Windows\System32\MSVCP140.dll" -Destination "."

# Si existe esta DLL, copiarla también
if (Test-Path "C:\Windows\System32\VCRUNTIME140_1.dll") {
    Copy-Item "C:\Windows\System32\VCRUNTIME140_1.dll" -Destination "."
}

Write-Host "DLLs copiadas exitosamente!" -ForegroundColor Green
```

3. **Recompilar el instalador:**

```powershell
cd "C:\Users\TeVsKo\Desktop\SpaceParkMaster"
.\build.bat
```

4. **Probar en PC limpia**

---

## 📋 Verificación

Después de implementar la solución:

1. ✅ Verificar que las DLLs están en la carpeta de PHPDesktop
2. ✅ Compilar nuevo instalador
3. ✅ Probar instalación en PC limpia
4. ✅ Confirmar que no aparece el error

---

## 🔍 Archivos Afectados

### Con Opción 1 (VC++ Redistributable):
- `packaging/SpaceParkInstaller.iss` (modificar)
- `packaging/redist/vc_redist.x64.exe` (agregar)

### Con Opción 2 (Copiar DLLs):
- `packages_wamp/phpdesktop-chrome-57.0-rc-php-7.1.3/VCRUNTIME140.dll` (agregar)
- `packages_wamp/phpdesktop-chrome-57.0-rc-php-7.1.3/MSVCP140.dll` (agregar)
- `packages_wamp/phpdesktop-chrome-57.0-rc-php-7.1.3/VCRUNTIME140_1.dll` (agregar si existe)

---

## ⚠️ Notas Importantes

- **Opción 1** es más profesional pero requiere descargar el instalador de VC++
- **Opción 2** es más simple y rápida, pero las DLLs deben estar en tu PC
- Las DLLs se incluirán automáticamente en futuros builds
- No afecta instalaciones existentes, solo nuevas instalaciones

---

## 🆘 Si el Problema Persiste

Si después de implementar la solución el error continúa:

1. Verificar que las DLLs están en la carpeta correcta
2. Verificar la arquitectura (32-bit vs 64-bit)
3. Probar instalar VC++ Redistributable manualmente en la PC de prueba
4. Revisar logs del instalador en `%TEMP%\Setup Log YYYY-MM-DD #XXX.txt`
