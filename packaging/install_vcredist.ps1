# install_vcredist.ps1
# Script para descargar Visual C++ Redistributable

$ErrorActionPreference = "Stop"

Write-Host "===========================================" -ForegroundColor Cyan
Write-Host "  DESCARGANDO VC++ REDISTRIBUTABLE" -ForegroundColor Cyan
Write-Host "===========================================" -ForegroundColor Cyan

# Crear carpeta redist si no existe
$redistFolder = Join-Path $PSScriptRoot "redist"
if (!(Test-Path $redistFolder)) {
    New-Item -ItemType Directory -Path $redistFolder | Out-Null
    Write-Host "[+] Carpeta 'redist' creada" -ForegroundColor Green
}

# URL de descarga (Microsoft oficial)
$url = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
$output = Join-Path $redistFolder "vc_redist.x64.exe"

# Verificar si ya existe
if (Test-Path $output) {
    Write-Host "[!] El archivo ya existe: $output" -ForegroundColor Yellow
    $response = Read-Host "¿Descargar nuevamente? (s/n)"
    if ($response -ne 's' -and $response -ne 'S') {
        Write-Host "[*] Usando archivo existente" -ForegroundColor Cyan
        exit 0
    }
}

# Descargar
Write-Host "[*] Descargando desde: $url" -ForegroundColor Cyan
try {
    Invoke-WebRequest -Uri $url -OutFile $output -UseBasicParsing
    Write-Host "[+] Descarga completada: $output" -ForegroundColor Green
    
    # Mostrar tamaño del archivo
    $fileSize = (Get-Item $output).Length / 1MB
    Write-Host "[*] Tamaño: $([math]::Round($fileSize, 2)) MB" -ForegroundColor Cyan
    
}
catch {
    Write-Host "[-] Error al descargar: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "===========================================" -ForegroundColor Green
Write-Host "  DESCARGA EXITOSA" -ForegroundColor Green
Write-Host "===========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Archivo guardado en: $output" -ForegroundColor White
Write-Host ""
Write-Host "Ahora puedes compilar el instalador con build.bat" -ForegroundColor Yellow
