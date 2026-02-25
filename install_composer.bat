@echo off
REM Script para descargar e instalar PHPMailer manualmente (sin Composer)
echo ================================================
echo  Instalador Manual de PHPMailer
echo ================================================
echo.

REM Verificar si ya existe vendor/phpmailer
if exist vendor\phpmailer\phpmailer (
    echo [INFO] PHPMailer ya esta instalado en vendor/phpmailer/phpmailer
    echo.
    choice /C YN /M "Desea reinstalar PHPMailer"
    if errorlevel 2 goto :end
    echo.
    echo [INFO] Eliminando instalacion anterior...
    rmdir /S /Q vendor\phpmailer
)

echo [INFO] Creando estructura de directorios...
mkdir vendor\phpmailer\phpmailer\src 2>nul

echo [INFO] Descargando PHPMailer desde GitHub...
echo.

REM Descargar PHPMailer usando PowerShell
powershell -Command "& {Invoke-WebRequest -Uri 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip' -OutFile 'phpmailer.zip'}"

if not exist phpmailer.zip (
    echo [ERROR] No se pudo descargar PHPMailer.
    echo Por favor, descargue manualmente desde: https://github.com/PHPMailer/PHPMailer/releases
    pause
    exit /b 1
)

echo [OK] PHPMailer descargado.
echo [INFO] Extrayendo archivos...

REM Extraer ZIP usando PowerShell
powershell -Command "& {Expand-Archive -Path 'phpmailer.zip' -DestinationPath 'temp_phpmailer' -Force}"

if not exist temp_phpmailer (
    echo [ERROR] No se pudo extraer el archivo ZIP.
    del phpmailer.zip
    pause
    exit /b 1
)

echo [OK] Archivos extraidos.
echo [INFO] Moviendo archivos a vendor/...

REM Mover archivos a la ubicación correcta
xcopy /E /I /Y temp_phpmailer\PHPMailer-6.9.1\src vendor\phpmailer\phpmailer\src
xcopy /Y temp_phpmailer\PHPMailer-6.9.1\*.php vendor\phpmailer\phpmailer\

REM Limpiar archivos temporales
echo [INFO] Limpiando archivos temporales...
rmdir /S /Q temp_phpmailer
del phpmailer.zip

REM Crear autoload.php personalizado
echo [INFO] Creando autoloader...
(
echo ^<?php
echo // Autoloader simple para PHPMailer
echo spl_autoload_register^(function ^($class^) {
echo     $prefix = 'PHPMailer\\PHPMailer\\';
echo     $base_dir = __DIR__ . '/phpmailer/phpmailer/src/';
echo     $len = strlen^($prefix^);
echo     if ^(strncmp^($prefix, $class, $len^) !== 0^) {
echo         return;
echo     }
echo     $relative_class = substr^($class, $len^);
echo     $file = $base_dir . str_replace^('\\', '/', $relative_class^) . '.php';
echo     if ^(file_exists^($file^)^) {
echo         require $file;
echo     }
echo }^);
) > vendor\autoload.php

if not exist vendor\autoload.php (
    echo [ERROR] No se pudo crear el autoloader.
    pause
    exit /b 1
)

echo.
echo ================================================
echo  Instalacion completada exitosamente
echo ================================================
echo.
echo [OK] PHPMailer instalado en: vendor/phpmailer/phpmailer/
echo [OK] Autoloader creado en: vendor/autoload.php
echo.
echo Proximos pasos:
echo 1. Configure SMTP en: Admin Panel -^> Configuraciones -^> SMTP
echo 2. Pruebe el envio con el script de prueba
echo.

:end
pause
