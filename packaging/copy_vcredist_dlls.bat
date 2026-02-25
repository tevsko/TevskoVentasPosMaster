@echo off
REM copy_vcredist_dlls.bat
REM Copia las DLLs de Visual C++ Runtime a PHPDesktop

echo ==========================================
echo   COPIANDO DLLS DE VC++ RUNTIME
echo ==========================================

REM La ruta de PHPDesktop según build.bat
set PHPDESKTOP_DIR=C:\phpdesktop-chrome-130.1-php-8.3

if not exist "%PHPDESKTOP_DIR%" (
    echo [ERROR] No se encontro la carpeta de PHPDesktop
    echo Ruta esperada: %PHPDESKTOP_DIR%
    echo.
    echo Verifica que PHPDesktop este instalado en esa ubicacion
    echo o edita este script para usar la ruta correcta.
    pause
    exit /b 1
)

echo [*] Carpeta PHPDesktop: %PHPDESKTOP_DIR%
echo.

REM Copiar VCRUNTIME140.dll
if exist "C:\Windows\System32\VCRUNTIME140.dll" (
    copy /Y "C:\Windows\System32\VCRUNTIME140.dll" "%PHPDESKTOP_DIR%\"
    echo [+] VCRUNTIME140.dll copiada
) else (
    echo [-] VCRUNTIME140.dll no encontrada en System32
    echo     Instala Visual C++ Redistributable primero
)

REM Copiar MSVCP140.dll
if exist "C:\Windows\System32\MSVCP140.dll" (
    copy /Y "C:\Windows\System32\MSVCP140.dll" "%PHPDESKTOP_DIR%\"
    echo [+] MSVCP140.dll copiada
) else (
    echo [-] MSVCP140.dll no encontrada en System32
)

REM Copiar VCRUNTIME140_1.dll (si existe)
if exist "C:\Windows\System32\VCRUNTIME140_1.dll" (
    copy /Y "C:\Windows\System32\VCRUNTIME140_1.dll" "%PHPDESKTOP_DIR%\"
    echo [+] VCRUNTIME140_1.dll copiada
) else (
    echo [!] VCRUNTIME140_1.dll no encontrada (opcional)
)

echo.
echo ==========================================
echo   DLLS COPIADAS EXITOSAMENTE
echo ==========================================
echo.
echo Ahora puedes compilar el instalador con build.bat
echo Las DLLs se incluiran automaticamente en el EXE.
echo.
pause
