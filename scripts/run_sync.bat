@echo off
REM Run sync worker and append logs
SET PHP="C:\wamp64\bin\php\php8.3.14\php.exe"
SET APP_DIR=%~dp0\..
cd /d %APP_DIR%
%PHP% sync_worker.php >> logs\sync_worker.log 2>&1
