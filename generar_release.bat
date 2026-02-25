@echo off
echo Iniciando generacion de release web...
powershell.exe -ExecutionPolicy Bypass -File ".\prepare_release.ps1"
echo.
echo Proceso finalizado.
pause
