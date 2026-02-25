# download_vcredist.ps1
# Descarga el Visual C++ Redistributable para incluirlo en el instalador

param(
    [string]$DestinationPath = "$PSScriptRoot\redist"
)

$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Descargando VC++ Redistributable" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Crear carpeta redist si no existe
if (!(Test-Path $DestinationPath)) {
    New-Item -ItemType Directory -Path $DestinationPath | Out-Null
    Write-Host "[+] Carpeta redist creada" -ForegroundColor Green
}

$vcRedistPath = Join-Path $DestinationPath "vc_redist.x64.exe"

# Verificar si ya existe
if (Test-Path $vcRedistPath) {
    Write-Host "[*] VC++ Redistributable ya existe: $vcRedistPath" -ForegroundColor Yellow
    $fileInfo = Get-Item $vcRedistPath
    Write-Host "    Tamaño: $([math]::Round($fileInfo.Length / 1MB, 2)) MB" -ForegroundColor Gray
    Write-Host "    Fecha: $($fileInfo.LastWriteTime)" -ForegroundColor Gray
}
else {
    # Descargar
    Write-Host "[*] Descargando VC++ Redistributable..." -ForegroundColor Yellow
    $url = "https://aka.ms/vs/17/release/vc_redist.x64.exe"
    
    try {
        Invoke-WebRequest -Uri $url -OutFile $vcRedistPath -UseBasicParsing
        $fileInfo = Get-Item $vcRedistPath
        Write-Host "[+] Descargado exitosamente: $([math]::Round($fileInfo.Length / 1MB, 2)) MB" -ForegroundColor Green
    }
    catch {
        Write-Host "[-] Error al descargar: $_" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  VC++ Redistributable listo" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
