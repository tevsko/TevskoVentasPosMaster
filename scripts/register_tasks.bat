@echo off
REM Register scheduled tasks to run workers every 5 minutes (requires elevated privileges)
SET PHP_PATH=C:\wamp64\bin\php\php8.3.14\php.exe
SET WORK_DIR=%~dp0\..

REM Create logs directory if not exists
if not exist "%WORK_DIR%logs" mkdir "%WORK_DIR%logs"

REM Register sync worker (every 1 minute) running as SYSTEM
schtasks /Create /SC MINUTE /MO 1 /TN "SpacePark Sync Worker" /TR "\"%PHP_PATH%\" \"%WORK_DIR%\sync_worker.php\"" /RU "SYSTEM" /RL HIGHEST /F

REM Register email worker running as SYSTEM
schtasks /Create /SC HOURLY /MO 1 /TN "SpacePark Email Worker" /TR "\"%PHP_PATH%\" \"%WORK_DIR%\email_worker.php\"" /RU "SYSTEM" /RL HIGHEST /F

echo Tasks registered.
