param(
    [string]$IsccPath = 'C:\\Program Files (x86)\\Inno Setup 6\\ISCC.exe',
    [string]$Version = '1.0.0',
    [string]$PhpDesktopPath = ''
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$BuildDir = Join-Path $Root 'build'
# Allow passing PhpDesktop path via parameter for non-interactive runs
if ([string]::IsNullOrWhiteSpace($PhpDesktopPath)) {
    $PhpDesktopSource = Read-Host 'Drag/enter path to your PHPDesktop folder (containing phpdesktop.exe)'
}
else {
    $PhpDesktopSource = $PhpDesktopPath
}
if (-not (Test-Path $PhpDesktopSource)) { Write-Error "PHPDesktop folder not found at $PhpDesktopSource"; exit 1 }

# Prepare build folder
if (Test-Path $BuildDir) { Remove-Item $BuildDir -Recurse -Force }
New-Item -ItemType Directory -Path (Join-Path $BuildDir 'phpdesktop') | Out-Null

# ========================================
# DESCARGAR VC++ REDISTRIBUTABLE
# ========================================
Write-Host "Descargando VC++ Redistributable..." -ForegroundColor Cyan
& "$Root\download_vcredist.ps1" -DestinationPath "$Root\redist"
if ($LASTEXITCODE -ne 0) {
    Write-Warning "Error descargando VC++ Redistributable, continuando sin el..."
}


# Copy PHPDesktop runtime into build
Write-Host "Copying PHPDesktop runtime..."
robocopy "$PhpDesktopSource" (Join-Path $BuildDir 'phpdesktop') /MIR | Out-Null

# Copy project web files into phpdesktop www
Write-Host "Copying project files into PHPDesktop www..."
$ProjectRoot = Resolve-Path "$Root\.." | Select-Object -ExpandProperty Path
robocopy "$ProjectRoot" (Join-Path $BuildDir 'phpdesktop\www') /MIR /XD packaging .git .github out release_web .vscode tests docs data /XF webhook_mp.php fix_missing_column.php signup.php landing.php success_setup.php migration_*.php .gitignore *.md *.log tmp_* | Out-Null

# FORCE SQLITE FOR CLIENT BUILD (Using APPDATA for write permissions)
$DbConfig = Join-Path $BuildDir 'phpdesktop\www\config\db.php'
Write-Host "Configuring Client DB to SQLite (APPDATA) in $DbConfig"
$Content = Get-Content $DbConfig -Raw
$Content = $Content -replace "define\('DB_DRIVER', 'mysql'\)", "define('DB_DRIVER', 'sqlite')"
# Replace the default definition with one that uses APPDATA
$Content = $Content -replace "define\('DB_SQLITE_FILE'.*?\);", "define('DB_SQLITE_FILE', getenv('APPDATA') . '/SpacePark/data/data.sqlite');"
Set-Content -Path $DbConfig -Value $Content

# Copy helper scripts into app root
Copy-Item "$Root\postinstall.bat" (Join-Path $BuildDir 'phpdesktop') -Force
Copy-Item "$ProjectRoot\scripts\register_tasks.bat" (Join-Path $BuildDir 'phpdesktop') -Force

# ========================================
# APLICAR CORRECCIONES DE PHP Y ICONO
# ========================================

Write-Host "Aplicando correcciones de PHP..." -ForegroundColor Cyan

# Corregir php.ini para evitar Error 500
$phpIniPath = Join-Path $BuildDir 'phpdesktop\php\php.ini'
if (Test-Path $phpIniPath) {
    $phpIniContent = Get-Content $phpIniPath -Raw
    
    # Configuraciones para evitar headers malformed
    $phpIniContent = $phpIniContent -replace '(?m)^display_errors\s*=.*', 'display_errors = Off'
    $phpIniContent = $phpIniContent -replace '(?m)^display_startup_errors\s*=.*', 'display_startup_errors = Off'
    $phpIniContent = $phpIniContent -replace '(?m)^log_errors\s*=.*', 'log_errors = On'
    $phpIniContent = $phpIniContent -replace '(?m)^output_buffering\s*=.*', 'output_buffering = 65536'
    $phpIniContent = $phpIniContent -replace '(?m)^implicit_flush\s*=.*', 'implicit_flush = Off'
    
    # CRÍTICO: Suprimir TODOS los tipos de errores en display
    if ($phpIniContent -notmatch 'html_errors\s*=') {
        $phpIniContent += "`nhtml_errors = Off"
    }
    else {
        $phpIniContent = $phpIniContent -replace '(?m)^html_errors\s*=.*', 'html_errors = Off'
    }
    
    # Desactivar warnings y notices
    if ($phpIniContent -notmatch 'error_reporting\s*=') {
        $phpIniContent += "`nerror_reporting = E_ERROR | E_PARSE"
    }
    else {
        $phpIniContent = $phpIniContent -replace '(?m)^error_reporting\s*=.*', 'error_reporting = E_ERROR | E_PARSE'
    }
    
    # Agregar configuraciones si no existen
    if ($phpIniContent -notmatch 'error_log\s*=') {
        $phpIniContent += "`nerror_log = php_errors.log"
    }
    if ($phpIniContent -notmatch 'max_input_vars\s*=') {
        $phpIniContent += "`nmax_input_vars = 5000"
    }
    
    # NOTA: auto_prepend_file no funciona correctamente con php-cgi en PHPDesktop
    # En su lugar, confiamos en la configuración agresiva de php.ini arriba
    # y en los require_once en los archivos de entrada
    # if ($phpIniContent -notmatch 'auto_prepend_file\s*=') {
    #     $phpIniContent += "`nauto_prepend_file = `"C:/Program Files (x86)/SpacePark/www/bootstrap_error_handler.php`""
    #     Write-Host "[+] auto_prepend_file configurado con ruta absoluta" -ForegroundColor Green
    # }
    
    Set-Content -Path $phpIniPath -Value $phpIniContent -NoNewline
    Write-Host "[+] php.ini configurado correctamente" -ForegroundColor Green
}
else {
    Write-Warning "php.ini no encontrado en $phpIniPath"
}

# ========================================
# ELIMINAR LOCALES INNECESARIOS DE PHP
# ========================================
Write-Host "Eliminando locales innecesarios de PHP (dejando solo en y es)..." -ForegroundColor Cyan

$localesPath = Join-Path $BuildDir 'phpdesktop\php\lib\locale'
if (Test-Path $localesPath) {
    Get-ChildItem $localesPath -Directory | Where-Object {
        $_.Name -notmatch '^(en|es)' -and $_.Name -ne 'en-GB' -and $_.Name -ne 'en-US' -and $_.Name -ne 'es-419'
    } | ForEach-Object {
        Remove-Item $_.FullName -Recurse -Force
        Write-Host "  [-] Eliminado: $($_.Name)" -ForegroundColor DarkGray
    }
    
    $remainingCount = (Get-ChildItem $localesPath -Directory).Count
    Write-Host "[+] Locales reducidos. Quedan: $remainingCount carpetas" -ForegroundColor Green
}
else {
    Write-Host "  [!] Carpeta de locales no encontrada en: $localesPath" -ForegroundColor Yellow
}

# Copiar icono de astronauta
Write-Host "Aplicando icono de astronauta..." -ForegroundColor Cyan
$iconSource = "$Root\..\assets\spacepark.ico"
$iconDest = Join-Path $BuildDir 'phpdesktop\spacepark.ico'

# Si no existe el icono en assets, generarlo desde la imagen
if (!(Test-Path $iconSource)) {
    $pngPath = "C:\Users\TeVsKo\.gemini\antigravity\brain\c7323986-16ed-4b11-bdd7-19e8098b59d8\astronaut_icon_1770250277554.png"
    if (Test-Path $pngPath) {
        Write-Host "[*] Convirtiendo PNG a ICO..." -ForegroundColor Yellow
        try {
            Add-Type -AssemblyName System.Drawing
            $img = [System.Drawing.Image]::FromFile($pngPath)
            $bitmap = New-Object System.Drawing.Bitmap 256, 256
            $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.DrawImage($img, 0, 0, 256, 256)
            $icon = [System.Drawing.Icon]::FromHandle($bitmap.GetHicon())
            $stream = [System.IO.File]::Create($iconDest)
            $icon.Save($stream)
            $stream.Close()
            $graphics.Dispose()
            $bitmap.Dispose()
            $img.Dispose()
            Write-Host "[+] Icono de astronauta creado" -ForegroundColor Green
        }
        catch {
            Write-Warning "No se pudo crear el icono: $_"
        }
    }
    else {
        Write-Warning "Imagen PNG del astronauta no encontrada"
    }
}
else {
    Copy-Item $iconSource $iconDest -Force
    Write-Host "[+] Icono de astronauta copiado" -ForegroundColor Green
}

# ========================================
# CREAR CARPETA UPLOAD PARA SERVIDOR
# ========================================

Write-Host "Creando carpeta upload para servidor..." -ForegroundColor Cyan

$UploadDir = Join-Path $Root '..\out\upload'
if (!(Test-Path $UploadDir)) {
    New-Item -ItemType Directory -Path $UploadDir -Force | Out-Null
}

# Comprimir PHPDesktop para descarga online
$phpdesktopSource = Join-Path $BuildDir 'phpdesktop'
$phpdesktopZip = Join-Path $UploadDir 'phpdesktop.zip'

Write-Host "[*] Comprimiendo PHPDesktop runtime..." -ForegroundColor Yellow
Compress-Archive -Path "$phpdesktopSource\*" -DestinationPath $phpdesktopZip -Force

# Crear manifest.json con checksums
$manifest = @{
    version   = $Version
    files     = @{
        phpdesktop = @{
            filename = "phpdesktop.zip"
            size     = (Get-Item $phpdesktopZip).Length
            checksum = (Get-FileHash $phpdesktopZip -Algorithm SHA256).Hash
        }
    }
    generated = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
}

$manifestPath = Join-Path $UploadDir 'manifest.json'
$manifest | ConvertTo-Json -Depth 10 | Set-Content $manifestPath

# Crear version.txt
$versionPath = Join-Path $UploadDir 'version.txt'
Set-Content $versionPath $Version

Write-Host "[+] Carpeta upload creada en: $UploadDir" -ForegroundColor Green
Write-Host "    - phpdesktop.zip: $([math]::Round((Get-Item $phpdesktopZip).Length / 1MB, 2)) MB" -ForegroundColor Gray
Write-Host "    - manifest.json" -ForegroundColor Gray
Write-Host "    - version.txt" -ForegroundColor Gray

# ========================================
# COMPILAR INSTALADORES
# ========================================

# Check ISCC
if (-not (Test-Path $IsccPath)) { 
    Write-Warning "ISCC not found at $IsccPath. Installer build will fail unless ISCC is installed or path overridden." 
}

# Compilar instalador OFFLINE (con PHPDesktop incluido)
$IssOfflinePath = Join-Path $Root 'SpaceParkInstaller.iss'
Write-Host "`nCompilando instalador OFFLINE (version $Version)..." -ForegroundColor Cyan
& "$IsccPath" /DMyAppVersion=$Version "$IssOfflinePath"

if ($LASTEXITCODE -eq 0) {
    $offlineExe = Join-Path $Root "..\out\SpaceParkInstaller-$Version-Offline.exe"
    if (Test-Path $offlineExe) {
        $offlineSize = [math]::Round((Get-Item $offlineExe).Length / 1MB, 2)
        Write-Host "[+] Instalador Offline generado: $offlineSize MB" -ForegroundColor Green
    }
}

# Compilar instalador ONLINE (descarga PHPDesktop)
$IssOnlinePath = Join-Path $Root 'SpaceParkInstaller-Online.iss'
if (Test-Path $IssOnlinePath) {
    Write-Host "`nCompilando instalador ONLINE (version $Version)..." -ForegroundColor Cyan
    & "$IsccPath" /DMyAppVersion=$Version "$IssOnlinePath"
    
    if ($LASTEXITCODE -eq 0) {
        $onlineExe = Join-Path $Root "..\out\SpaceParkInstaller-$Version-Online.exe"
        if (Test-Path $onlineExe) {
            $onlineSize = [math]::Round((Get-Item $onlineExe).Length / 1MB, 2)
            Write-Host "[+] Instalador Online generado: $onlineSize MB" -ForegroundColor Green
        }
    }
}
else {
    Write-Warning "SpaceParkInstaller-Online.iss no encontrado. Solo se compiló el instalador Offline."
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Build completo" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Instaladores en: packaging\out\" -ForegroundColor Gray
Write-Host "Archivos para servidor en: packaging\out\upload\" -ForegroundColor Gray
