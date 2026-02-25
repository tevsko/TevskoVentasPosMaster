# Prepara una carpeta limpia release_web lista para subir al hosting
# Este script toma los archivos del proyecto y los copia a release_web
# excluyendo herramientas de desarrollo, documentacion y archivos temporales

$ErrorActionPreference = "Stop"
$Source = $PSScriptRoot
$Dest = Join-Path $Source "release_web"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  PREPARANDO VERSION PARA WEB HOSTING" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# 1. Limpiar version anterior
if (Test-Path $Dest) {
    Write-Host "[-] Borrando carpeta release_web anterior..." -ForegroundColor Yellow
    Remove-Item $Dest -Recurse -Force
}

# 2. Crear nueva carpeta
New-Item -ItemType Directory -Path $Dest | Out-Null
Write-Host "[+] Carpeta release_web creada." -ForegroundColor Green

# 3. Definir exclusiones
$ItemsToExclude = @(
    "release_web",
    ".git",
    ".gitignore",
    ".vscode",
    "packaging",
    "scripts",
    "docs",
    "data",
    "out",
    "build",
    "tests",
    "node_modules"
)

$PatternsToExclude = @(
    "*.md",
    "*.ps1",
    "*.bat",
    "*.log",
    "tmp_*",
    ".*"
)

# 4. Copiar archivos
Write-Host "[*] Copiando archivos..."

$Items = Get-ChildItem -Path $Source

foreach ($Item in $Items) {
    $Name = $Item.Name
    
    if ($ItemsToExclude -contains $Name) {
        Write-Host "    [Omitido] $Name" -ForegroundColor Gray
        continue
    }

    if ($Name -eq ".htaccess") {
        Write-Host "    [Copiando] $Name" -ForegroundColor Cyan
        Copy-Item -Path $Item.FullName -Destination $Dest -Recurse
        continue
    }

    $Skip = $false
    foreach ($Pattern in $PatternsToExclude) {
        if ($Name -like $Pattern) {
            $Skip = $true
            break
        }
    }

    if ($Skip) {
        Write-Host "    [Omitido] $Name" -ForegroundColor Gray
        continue
    }

    Write-Host "    [Copiando] $Name" -ForegroundColor Green
    Copy-Item -Path $Item.FullName -Destination $Dest -Recurse
}

# 5. Verificar carpetas importantes
$ImportantFolders = @("client", "api", "admin", "pos", "src", "config", "vendor", "assets", "mobile", "migrations")
foreach ($Folder in $ImportantFolders) {
    $FolderPath = Join-Path $Dest $Folder
    if (Test-Path $FolderPath) {
        Write-Host "    [OK] Carpeta $Folder incluida" -ForegroundColor Green
    }
    else {
        $SourceFolder = Join-Path $Source $Folder
        if (Test-Path $SourceFolder) {
            Write-Host "    [!] Carpeta $Folder faltaba, copiando..." -ForegroundColor Yellow
            Copy-Item -Path $SourceFolder -Destination $Dest -Recurse
        }
    }
}

# 6. Verificar archivos PHP importantes
$ImportantFiles = @(
    "success_setup.php",
    "landing.php",
    "login.php",
    "index.php"
)
foreach ($File in $ImportantFiles) {
    $FilePath = Join-Path $Dest $File
    if (Test-Path $FilePath) {
        Write-Host "    [OK] Archivo $File incluido" -ForegroundColor Green
    }
    else {
        Write-Host "    [!] Archivo $File NO encontrado" -ForegroundColor Red
    }
}

# 7. Eliminar config/db.php del release
$configDbPath = Join-Path $Dest "config\db.php"
if (Test-Path $configDbPath) {
    Remove-Item $configDbPath -Force
    Write-Host "[!] config/db.php eliminado del release" -ForegroundColor Yellow
}

# 8. Verificar modulo movil
$MobilePath = Join-Path $Source "mobile"
if (Test-Path $MobilePath) {
    Write-Host ""
    Write-Host "[*] Verificando modulo movil..." -ForegroundColor Cyan
    
    $MobileFiles = @(
        "mobile\index.html",
        "mobile\report.html",
        "mobile\manifest.json",
        "mobile\sw.js",
        "api\mobile\auth.php",
        "api\mobile\submit_report.php",
        "admin\arcade_config.php",
        "admin\arcade_reports.php"
    )
    
    foreach ($File in $MobileFiles) {
        $FilePath = Join-Path $Dest $File
        if (Test-Path $FilePath) {
            Write-Host "    [OK] $File" -ForegroundColor Green
        }
        else {
            Write-Host "    [!] $File NO encontrado" -ForegroundColor Red
        }
    }
    
    $ArcadePhotosPath = Join-Path $Dest "assets\uploads\arcade\photos"
    if (-not (Test-Path $ArcadePhotosPath)) {
        New-Item -ItemType Directory -Path $ArcadePhotosPath -Force | Out-Null
        Write-Host "    [+] Carpeta arcade/photos creada" -ForegroundColor Green
    }
    else {
        Write-Host "    [OK] Carpeta arcade/photos existe" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  LISTO!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  Los archivos para subir estan en:" -ForegroundColor White
Write-Host "  $Dest" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Archivos incluidos:" -ForegroundColor White
Write-Host "  - success_setup.php (con botones de descarga)" -ForegroundColor Gray
Write-Host "  - client/downloads.php (panel de descargas)" -ForegroundColor Gray
Write-Host "  - api/get_installers.php (API de versiones)" -ForegroundColor Gray
if (Test-Path $MobilePath) {
    Write-Host "  - mobile/ (PWA para control de arcade)" -ForegroundColor Gray
    Write-Host "  - api/mobile/ (APIs del modulo movil)" -ForegroundColor Gray
}
Write-Host "==========================================" -ForegroundColor Cyan
