# download_runtime.ps1
# Script para descargar PHPDesktop runtime durante instalación online

param(
    [string]$InstallDir,
    [string]$DownloadURL
)

$ErrorActionPreference = "Stop"

# Crear ventana de progreso
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing

$form = New-Object System.Windows.Forms.Form
$form.Text = "SpacePark - Instalador Online"
$form.Size = New-Object System.Drawing.Size(500, 200)
$form.StartPosition = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox = $false
$form.MinimizeBox = $false
$form.TopMost = $true

$label = New-Object System.Windows.Forms.Label
$label.Location = New-Object System.Drawing.Point(20, 20)
$label.Size = New-Object System.Drawing.Size(460, 30)
$label.Text = "Descargando SpacePark POS Ventas..."
$label.Font = New-Object System.Drawing.Font("Segoe UI", 10, [System.Drawing.FontStyle]::Bold)
$form.Controls.Add($label)

$progressBar = New-Object System.Windows.Forms.ProgressBar
$progressBar.Location = New-Object System.Drawing.Point(20, 60)
$progressBar.Size = New-Object System.Drawing.Size(460, 30)
$progressBar.Style = "Continuous"
$form.Controls.Add($progressBar)

$statusLabel = New-Object System.Windows.Forms.Label
$statusLabel.Location = New-Object System.Drawing.Point(20, 100)
$statusLabel.Size = New-Object System.Drawing.Size(460, 20)
$statusLabel.Text = "Iniciando descarga..."
$form.Controls.Add($statusLabel)

$detailLabel = New-Object System.Windows.Forms.Label
$detailLabel.Location = New-Object System.Drawing.Point(20, 125)
$detailLabel.Size = New-Object System.Drawing.Size(460, 20)
$detailLabel.Text = ""
$detailLabel.ForeColor = [System.Drawing.Color]::Gray
$form.Controls.Add($detailLabel)

$form.Show()
$form.Refresh()

try {
    # URLs de descarga
    $zipUrl = "${DownloadURL}phpdesktop.zip"
    $manifestUrl = "${DownloadURL}manifest.json"
    
    # Archivos temporales
    $zipFile = Join-Path $env:TEMP "phpdesktop.zip"
    $manifestFile = Join-Path $env:TEMP "manifest.json"
    
    # Descargar manifest
    $statusLabel.Text = "Descargando información del paquete..."
    $form.Refresh()
    
    $webClient = New-Object System.Net.WebClient
    $webClient.DownloadFile($manifestUrl, $manifestFile)
    
    # Descargar PHPDesktop con barra de progreso
    $statusLabel.Text = "Descargando SpacePark POS Ventas (esto puede tardar varios minutos)..."
    $form.Refresh()
    
    $webClient = New-Object System.Net.WebClient
    
    # Event handler para actualizar progreso
    $webClient.add_DownloadProgressChanged({
            param($sender, $e)
            $progressBar.Value = $e.ProgressPercentage
        
            $receivedMB = [math]::Round($e.BytesReceived / 1MB, 1)
            $totalMB = [math]::Round($e.TotalBytesToReceive / 1MB, 1)
        
            $statusLabel.Text = "Descargando: $($e.ProgressPercentage)% completado"
            $detailLabel.Text = "$receivedMB MB de $totalMB MB"
        
            $form.Refresh()
        })
    
    # Descargar archivo
    $downloadTask = $webClient.DownloadFileTaskAsync($zipUrl, $zipFile)
    
    while (-not $downloadTask.IsCompleted) {
        [System.Windows.Forms.Application]::DoEvents()
        Start-Sleep -Milliseconds 100
    }
    
    if ($downloadTask.IsFaulted) {
        throw $downloadTask.Exception
    }
    
    # Extraer archivos
    $progressBar.Value = 0
    $statusLabel.Text = "Extrayendo archivos..."
    $detailLabel.Text = "Por favor espere..."
    $form.Refresh()
    
    Expand-Archive -Path $zipFile -DestinationPath $InstallDir -Force
    
    $progressBar.Value = 100
    $statusLabel.Text = "Instalacion completada!"
    $detailLabel.Text = "SpacePark POS Ventas instalado correctamente"
    $form.Refresh()
    
    Start-Sleep -Seconds 1
    
    # Limpiar archivos temporales
    Remove-Item $zipFile -Force -ErrorAction SilentlyContinue
    Remove-Item $manifestFile -Force -ErrorAction SilentlyContinue
    
    $form.Close()
    exit 0
    
}
catch {
    $form.Close()
    
    # Mostrar mensaje de error
    [System.Windows.Forms.MessageBox]::Show(
        "Error descargando SpacePark POS Ventas: $($_.Exception.Message)`n`nPor favor, descargue el instalador Offline desde:`n${DownloadURL}SpaceParkInstaller-Offline.exe",
        "Error de Descarga",
        [System.Windows.Forms.MessageBoxButtons]::OK,
        [System.Windows.Forms.MessageBoxIcon]::Error
    )
    
    exit 1
}
