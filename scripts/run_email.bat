@echo off
REM Run email worker and append logs
SET PHP="C:\wamp64\bin\php\php8.3.14\php.exe"
SET APP_DIR=%~dp0\..
cd /d %APP_DIR%
%PHP% email_worker.php >> logs\email_worker.log 2>&1
