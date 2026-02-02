# SpacePark - Instalación Cliente (Windows) - PHPDesktop + SQLite

Objetivo
- Entregar una aplicación de escritorio que funcione offline, sincronice con la nube y no requiera WAMP/XAMPP.

Prerequisitos
- Windows 10/11 con permisos para instalar software.
- Instalador `.exe` (recomendado: PHPDesktop) que incluya PHP (8.2/8.3) y extensiones PDO, curl, json, openssl.

Instalación paso a paso (verificada)
1. Ejecutar instalador `SpacePark-Setup.exe` (instalador crea carpeta por defecto `C:\Program Files\SpacePark`).
2. Verificar archivos principales instalados:
   - `C:\Program Files\SpacePark\index.php`
   - `C:\Program Files\SpacePark\sync_worker.php`
   - `C:\Program Files\SpacePark\scripts\init_sqlite.php`
   - `C:\Program Files\SpacePark\data\data.sqlite`
   - `C:\Program Files\SpacePark\logs\`.
3. Inicializar base de datos local (si el instalador no lo hizo):
   - Abrir PowerShell como Administrador y ejecutar:
     - `cd "C:\Program Files\SpacePark"`
     - `php scripts/init_sqlite.php`
     - `php scripts/seed_plans_sqlite.php` (opcional, para planes de demo)
4. Configurar settings mínimos (2 opciones):
   - Desde Admin central (recomendado): ingresar `site_url` y `sync_api_token` en Admin → Billing.
   - O localmente (si no hay UI): `php scripts/set_sqlite_setting.php site_url "https://mi-nube.example"` and `php scripts/set_sqlite_setting.php sync_api_token "<TOKEN>"`
5. Registrar tareas (Task Scheduler) para los workers (ejecutar como Administrador):
   - Ejecutar el script incluido: `scripts\register_tasks.bat` (ejecutar en PowerShell/Command Prompt con permisos elevados).
   - Manual (ejemplo):
     - `schtasks /Create /SC MINUTE /MO 1 /TN "SpacePark Sync Worker" /TR "\"C:\\path\\to\\php.exe\" \"C:\\Program Files\\SpacePark\\sync_worker.php\"" /RU "SYSTEM" /RL HIGHEST /F`
6. Pruebas locales rápidas:
   - Crear una venta de prueba:
     - `php scripts/create_sqlite_sale_test.php`
   - Ejecutar manualmente worker (modo debugging): `php sync_worker.php`
   - Verificar que `sync_queue` quede vacía y que `sales.sync_status = 1` y que `sync_logs` muestre entradas.

Verificación post‑instalación (checklist)
- [ ] `data/data.sqlite` existe y es escribible.
- [ ] `php scripts/init_sqlite.php` no muestra errores.
- [ ] `site_url` y `sync_api_token` están configurados y correctos.
- [ ] Tareas en Task Scheduler registradas y visibles (run history activada si es posible).
- [ ] Se pueden ver logs en `logs/sync_worker.log` y `logs/email_worker.log`.

Diagnóstico y solución de problemas comunes
- Si `sync_worker` no se ejecuta automáticamente:
  - Revisar Task Scheduler y que la tarea corra como `SYSTEM` (o un usuario con permisos suficientes).
  - Ejecutar manualmente `php sync_worker.php` y revisar `logs/`.
- Si los items no se sincronizan (en la nube no aparecen):
  - Verificar `site_url` (debe ser accesible públicamente), `sync_api_token` y la conectividad.
- Si la app no inicia (PHPDesktop error): revisar `logs/phpdesktop.log` y la ruta a `php.exe` en la configuración del paquete.

Soporte técnico (qué enviar al equipo)
- Archivo `data/data.sqlite` (cuando se solicite).
- Logs: `logs/sync_worker.log`, `logs/email_worker.log`.
- Versión de la app (archivo `VERSION` en el directorio de instalación).

Notas de seguridad
- No enviar `data/data.sqlite` sin redacción; eliminar datos sensibles si se comparte públicamente.
- Mantener actualizado `sync_api_token` y `mp_webhook_secret` si se detecta compromiso.


