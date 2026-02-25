# apply_astronaut_icon.ps1
# Script automatizado para cambiar el icono a astronauta

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  CAMBIANDO ICONO A ASTRONAUTA" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Rutas
$pngPath = "C:\Users\TeVsKo\.gemini\antigravity\brain\c7323986-16ed-4b11-bdd7-19e8098b59d8\astronaut_icon_1770250277554.png"
$phpDesktopDir = "C:\phpdesktop-chrome-130.1-php-8.3"
$icoPath = Join-Path $phpDesktopDir "spacepark.ico"
$settingsPath = Join-Path $phpDesktopDir "settings.json"

# Verificar que existe el PNG
if (!(Test-Path $pngPath)) {
    Write-Host "[-] Error: No se encontro el archivo PNG" -ForegroundColor Red
    Write-Host "    Ruta: $pngPath" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "[*] Archivo PNG encontrado" -ForegroundColor Green

# Verificar que existe PHPDesktop
if (!(Test-Path $phpDesktopDir)) {
    Write-Host "[-] Error: No se encontro PHPDesktop" -ForegroundColor Red
    Write-Host "    Ruta: $phpDesktopDir" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "[*] PHPDesktop encontrado" -ForegroundColor Green
Write-Host ""

# Convertir PNG a ICO usando System.Drawing
Write-Host "[*] Convirtiendo PNG a ICO..." -ForegroundColor Cyan

try {
    Add-Type -AssemblyName System.Drawing
    
    # Cargar imagen PNG
    $img = [System.Drawing.Image]::FromFile($pngPath)
    
    # Crear bitmap con tamaño 256x256 (tamaño estándar para iconos)
    $bitmap = New-Object System.Drawing.Bitmap 256, 256
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $graphics.DrawImage($img, 0, 0, 256, 256)
    
    # Guardar como ICO
    $icon = [System.Drawing.Icon]::FromHandle($bitmap.GetHicon())
    $stream = [System.IO.File]::Create($icoPath)
    $icon.Save($stream)
    $stream.Close()
    
    # Limpiar recursos
    $graphics.Dispose()
    $bitmap.Dispose()
    $img.Dispose()
    
    Write-Host "[+] Icono ICO creado exitosamente" -ForegroundColor Green
    Write-Host "    Ubicacion: $icoPath" -ForegroundColor White
    
}
catch {
    Write-Host "[-] Error al convertir PNG a ICO: $_" -ForegroundColor Red
    pause
    exit 1
}

Write-Host ""

# Actualizar settings.json si existe
if (Test-Path $settingsPath) {
    Write-Host "[*] Actualizando settings.json..." -ForegroundColor Cyan
    
    try {
        $settings = Get-Content $settingsPath -Raw | ConvertFrom-Json
        $settings.main_window.icon = "spacepark.ico"
        $settings | ConvertTo-Json -Depth 10 | Set-Content $settingsPath
        
        Write-Host "[+] settings.json actualizado" -ForegroundColor Green
    }
    catch {
        Write-Host "[!] No se pudo actualizar settings.json automaticamente" -ForegroundColor Yellow
        Write-Host "    Edita manualmente y cambia 'icon' a 'spacepark.ico'" -ForegroundColor Yellow
    }
}
else {
    Write-Host "[!] settings.json no encontrado" -ForegroundColor Yellow
    Write-Host "    El icono se aplicara en el instalador" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  ICONO CAMBIADO EXITOSAMENTE" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Ahora recompila el instalador:" -ForegroundColor Yellow
Write-Host "  cd C:\Users\TeVsKo\Desktop\SpaceParkMaster" -ForegroundColor White
Write-Host "  .\build.bat" -ForegroundColor White
Write-Host ""
pause
