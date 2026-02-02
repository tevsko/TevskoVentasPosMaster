param([
    [string]$Version = '1.0.0'
)

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$BuildDir = Join-Path $Root 'build\phpdesktop'
$OutDir = Join-Path $Root 'out'
if (-not (Test-Path $BuildDir)) { Write-Error "Build folder missing. Run build_installer.ps1 first or copy PHPDesktop into build/phpdesktop."; exit 1 }
if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir | Out-Null }

$ZipName = Join-Path $OutDir "SpacePark-portable-$Version.zip"
Write-Host "Creating $ZipName..."
if (Test-Path $ZipName) { Remove-Item $ZipName }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($BuildDir, $ZipName)
Write-Host "Portable zip created: $ZipName"