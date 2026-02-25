@echo off
echo ========================================
echo   SpacePark - Generador de Instalador
echo ========================================
echo.
powershell -ExecutionPolicy Bypass -File .\packaging\build_installer.ps1 -PhpDesktopPath "C:\phpdesktop-chrome-130.1-php-8.3"
echo.
echo ========================================
echo   Instalador generado en: out\SpaceParkInstaller-1.0.0.exe
echo ========================================
pause
