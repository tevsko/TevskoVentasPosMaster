@echo off
REM Post-install script for SpacePark (runs as admin from installer)
set APPDIR=%~dp0

REM Find PHP executable (prefer packaged PHP in phpdesktop folder)
if exist "%APPDIR%php\php.exe" (
  set "PHP=%APPDIR%php\php.exe"
) else if exist "%SystemRoot%\System32\php.exe" (
  set "PHP=%SystemRoot%\System32\php.exe"
) else (
  echo PHP executable not found. Please ensure PHP is installed or included in package.
  goto :end
)

REM Ensure data and logs directories
if not exist "%APPDIR%data" mkdir "%APPDIR%data"
if not exist "%APPDIR%logs" mkdir "%APPDIR%logs"

REM Initialize SQLite schema
"%PHP%" "%APPDIR%scripts\init_sqlite.php"

REM Register scheduled tasks (run as SYSTEM)
schtasks /Create /SC MINUTE /MO 1 /TN "SpacePark Sync Worker" /TR "\"%PHP%\" \"%APPDIR%sync_worker.php\"" /RU "SYSTEM" /RL HIGHEST /F
schtasks /Create /SC HOURLY /MO 1 /TN "SpacePark Email Worker" /TR "\"%PHP%\" \"%APPDIR%email_worker.php\"" /RU "SYSTEM" /RL HIGHEST /F

:end
exit /b 0
