# fix_php_headers.ps1
# Soluciona el error "CGI program sent malformed or too big HTTP headers"

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  SOLUCIONANDO ERROR DE PHP HEADERS" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

$phpDesktopDir = "C:\phpdesktop-chrome-130.1-php-8.3"
$phpIniPath = Join-Path $phpDesktopDir "php\php.ini"

if (!(Test-Path $phpIniPath)) {
    Write-Host "[-] Error: No se encontro php.ini" -ForegroundColor Red
    Write-Host "    Ruta: $phpIniPath" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "[*] Archivo php.ini encontrado" -ForegroundColor Green
Write-Host ""

# Backup del php.ini original
$backupPath = "$phpIniPath.backup"
if (!(Test-Path $backupPath)) {
    Copy-Item $phpIniPath $backupPath
    Write-Host "[+] Backup creado: $backupPath" -ForegroundColor Green
}

# Leer contenido actual
$content = Get-Content $phpIniPath -Raw

# Configuraciones necesarias para evitar el error
$fixes = @{
    "display_errors"         = "Off"
    "display_startup_errors" = "Off"
    "error_reporting"        = "E_ALL & ~E_DEPRECATED & ~E_STRICT"
    "log_errors"             = "On"
    "error_log"              = "php_errors.log"
    "output_buffering"       = "4096"
    "implicit_flush"         = "Off"
    "max_input_vars"         = "5000"
}

Write-Host "[*] Aplicando configuraciones..." -ForegroundColor Cyan
Write-Host ""

foreach ($key in $fixes.Keys) {
    $value = $fixes[$key]
    
    # Buscar si ya existe la configuración
    if ($content -match "(?m)^$key\s*=") {
        # Reemplazar valor existente
        $content = $content -replace "(?m)^$key\s*=.*", "$key = $value"
        Write-Host "[~] Actualizado: $key = $value" -ForegroundColor Yellow
    }
    else {
        # Agregar nueva configuración
        $content += "`n$key = $value"
        Write-Host "[+] Agregado: $key = $value" -ForegroundColor Green
    }
}

# Guardar cambios
$content | Set-Content $phpIniPath -NoNewline

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  CONFIGURACION APLICADA" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Cambios realizados en php.ini:" -ForegroundColor White
Write-Host "  - display_errors = Off (oculta errores en headers)" -ForegroundColor Cyan
Write-Host "  - log_errors = On (guarda errores en archivo)" -ForegroundColor Cyan
Write-Host "  - output_buffering = 4096 (evita headers grandes)" -ForegroundColor Cyan
Write-Host ""
Write-Host "Ahora recompila el instalador:" -ForegroundColor Yellow
Write-Host "  cd C:\Users\TeVsKo\Desktop\SpaceParkMaster" -ForegroundColor White
Write-Host "  .\build.bat" -ForegroundColor White
Write-Host ""
pause
