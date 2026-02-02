# SpacePark - Instalación y Operación (Administrador / Proveedor)

Descripción corta
- Este documento cubre la instalación del servidor central (nube) que recibe sincronizaciones desde clientes POS y gestiona suscripciones con Mercado Pago.

Requisitos del servidor (CDMon / Cloud):
- PHP 8.2+ con extensiones: PDO, pdo_mysql, pdo_sqlite (para tests), curl, json, mbstring, openssl.
- MySQL / MariaDB (5.7+ recomendado).
- HTTPS con certificado válido (obligatorio para webhooks y seguridad).
- Acceso SSH y permisos para programar tareas y editar archivos.

Despliegue (pasos verificados):
1. Clonar el repo en el servidor:
   - git clone ... /var/www/spacepark && cd /var/www/spacepark
2. Copiar o crear `config/db.php` y ajustar variables (DB_HOST, DB_NAME, DB_USER, DB_PASS).
3. Ejecutar migraciones (ver salida):
   - php run_migration.php
   - Verificar que cada migration muestre "OK" y que no haya errores de DDL en transacciones.
4. Ajustar permisos (web server user):
   - chown -R www-data:www-data /var/www/spacepark
   - chmod -R 755 /var/www/spacepark
5. Configurar `site_url`, `sync_api_token`, `mp_access_token`, `mp_webhook_secret`, `mail_from` desde Admin → Facturación o por script si prefieres (ver más abajo).
6. Configurar Mercado Pago (Sandbox primero):
   - En tu cuenta MP: webhook target `https://<tu-dominio>/api/webhook_mp.php` (usar sandbox URL en pruebas).
   - Habilitar y anotar `mp_webhook_secret` si usás verificación HMAC.
7. Probar endpoints básicos:
   - Health: curl -I https://<tu-dominio>/health  (debe responder 200)
   - Ingest (test): curl -X POST -H "Authorization: Bearer <sync_api_token>" -H "Content-Type: application/json" -d '{"entries":[]}' https://<tu-dominio>/api/sync_ingest.php   - Provisioning: después de una compra, el sistema genera una *provisioning URL* y la envía por correo; también puedes verla en Admin → Facturación (columna "Provision"). La URL abre una página con el `sync_token` y un QR que el cliente puede usar para configurar su instalación.
Provisioning note: when a subscription is provisioned the server generates a per-tenant `sync_token` and (optionally) an `allowed_host`. The provision API returns the `sync_token` which must be delivered to the client installation (via email or the landing page). The client can import this token into their local install using the Admin Billing form or by POSTing to `api/import_token.php` with `sync_token` and optional `allowed_host`.
   - Webhook (simulación de pago): usar la herramienta de pruebas de Mercado Pago o curl con JSON representativo (ver sección "Pruebas").
8. Programar workers (ej. cron):
   - Sync worker (cada minuto): `* * * * * php /var/www/spacepark/sync_worker.php >> /var/www/spacepark/logs/sync_worker.log 2>&1`
   - Email worker (cada hora): `0 * * * * php /var/www/spacepark/email_worker.php >> /var/www/spacepark/logs/email_worker.log 2>&1`

Pruebas y validación rápida (checklist operativo):
- Ejecuta `php run_migration.php` y comprueba que no haya errores.
- Ejecuta localmente un servidor de pruebas (opcional): `php -S 127.0.0.1:8000 -t .` y prueba `http://127.0.0.1:8000/health`.
- Simular una venta desde un cliente o usar `scripts/create_subscription.php` y `scripts/provision_call.php` según el flujo.
- Verificar `sync_logs`, `sync_queue`, `outbox_emails` en la base de datos.
- Revisar logs en `logs/` después de ejecutar workers manualmente.

Comandos de ayuda (útiles):
- Ejecutar migraciones: `php run_migration.php`
- Ver estado DB: `php -r "require 'src/Database.php'; print_r(Database::getInstance()->getConnection()->query('SHOW TABLES')->fetchAll());"`
- Forzar provisioning: `php scripts/provision_call.php <subscription_id>`
- Probar webhook (ejemplo):
  curl -X POST -H "Content-Type: application/json" -d '{"data":{"id":"TEST_PAYMENT_ID"}}' https://<tu-dominio>/api/webhook_mp.php

Errores comunes y soluciones rápidas:
- MySQL no responde: revisar servicio (systemctl status mysql / service mysql status) y credenciales en `config/db.php`.
- Migración falló por DDL dentro de transacción: revisar salida de `php run_migration.php` y aplicar el SQL manualmente si es necesario.
- Webhook no llega: comprobar que el dominio sea accesible desde Internet y que el endpoint esté configurado con HTTPS.
- Ingest no acepta: verificar `sync_api_token` y el header Authorization.

Soporte y recolección de información (si hay un fallo):
- Logs: `logs/sync_worker.log`, `logs/email_worker.log` y tablas `sync_logs`.
- Solicitar: `dump` de la base de datos (mysqldump) y extracto de `sync_logs` y `sync_queue`.
- Si pedimos al cliente un diagnostico Windows, solicitar `data/data.sqlite` y los logs en `logs/`.

Checklist final (antes de pruebas en producción):
- [ ] Migraciones aplicadas y verificación de tablas.
- [ ] `site_url` configurado y accesible por Internet (HTTPS).
- [ ] `sync_api_token` y `mp_webhook_secret` configurados y guardados en Admin.
- [ ] Workers programados y con logs rotacionales.
- [ ] Pruebas de webhook en Sandbox exitosas.


