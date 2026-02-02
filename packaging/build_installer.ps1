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
} else {
    $PhpDesktopSource = $PhpDesktopPath
}
if (-not (Test-Path $PhpDesktopSource)) { Write-Error "PHPDesktop folder not found at $PhpDesktopSource"; exit 1 }

# Prepare build folder
if (Test-Path $BuildDir) { Remove-Item $BuildDir -Recurse -Force }
New-Item -ItemType Directory -Path (Join-Path $BuildDir 'phpdesktop') | Out-Null

# Copy PHPDesktop runtime into build
Write-Host "Copying PHPDesktop runtime..."
robocopy "$PhpDesktopSource" (Join-Path $BuildDir 'phpdesktop') /MIR | Out-Null

# Copy project web files into phpdesktop www
Write-Host "Copying project files into PHPDesktop www..."
$ProjectRoot = Resolve-Path "$Root\.." | Select-Object -ExpandProperty Path
robocopy "$ProjectRoot" (Join-Path $BuildDir 'phpdesktop\www') /MIR /XD packaging .git .github | Out-Null

# Copy helper scripts into app root
Copy-Item "$Root\postinstall.bat" (Join-Path $BuildDir 'phpdesktop') -Force
Copy-Item "$ProjectRoot\scripts\register_tasks.bat" (Join-Path $BuildDir 'phpdesktop') -Force

# Check ISCC
if (-not (Test-Path $IsccPath)) { Write-Warning "ISCC not found at $IsccPath. Installer build will fail unless ISCC is installed or path overridden." }

# Run Inno Setup Compiler
$IssPath = Join-Path $Root 'SpaceParkInstaller.iss'
Write-Host "Building installer (version $Version)..."
& "$IsccPath" /DMyAppVersion=$Version "$IssPath"
Write-Host "Build complete. Output in packaging\out by default."