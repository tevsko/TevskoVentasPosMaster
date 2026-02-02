# SpacePark POS — Documentación completa 🧩

**Resumen breve**

SpacePark es una solución POS flexible para entornos locales y en la nube que soporta operación *offline-first*, sincronización robusta, y empaquetado para Windows sin depender de XAMPP. Incluye control multi‑tenant, provisión segura mediante tokens/QR, y un panel de administración para gestión de tenants y licencias.

---

## 🔧 Características principales

- **Modo offline-first** con base de datos local SQLite por cliente y sincronización bidireccional con MySQL en el servidor central.
- **Outbox / sync_queue** para enviar cambios desde clientes a la nube de forma segura y eficiente.
- **Provisioning seguro**: URLs de un solo uso, QR y token de sincronización por tenant (`sync_token`).
- **Autenticación de ingestión**: API `api/sync_ingest.php` verifica `Bearer <sync_token>` y opcional `allowed_host`.
- **Migraciones** para soportar ambos motores (MySQL / SQLite) y ajustes driver‑específicos.
- **UUIDs** y manejo idempotente en ingest (INSERT OR IGNORE / INSERT IGNORE) para evitar duplicados.
- **Administración**: `admin/tenants.php`, gestión de rotación/revocación de tokens, reenvío de provisión.
- **Suscripciones** con Mercado Pago (sandbox) integradas en el flujo de provisionamiento.
- **Empaquetado Windows**: scripts PowerShell + Inno Setup y alternativa ZIP portable / PHPDesktop.
- **Tests**: pruebas E2E para flujo de provisión y migraciones SQLite.

---

## 🏗 Arquitectura (alto nivel)

- Cliente: PHP + SQLite local, interfaz empaquetada (PHPDesktop), usa `sync_queue`/`outbox`.
- Servidor: PHP + MySQL, endpoints REST (`api/sync_ingest.php`, `api/provision.php`, etc.).
- Mecanismo de sincronización: clientes empujan cambios en lotes; servidor los aplica idempotentemente y registra en `sync_logs`.

---

## 📁 Archivos y scripts clave

- `migrations/` — SQL de migración (incl. `005_tenant_tokens.sql`, `006_subscription_provision_secret.sql`).
- `src/` — clases principales: `TenantManager.php`, `Database.php`, `Auth.php`, `Uuid.php`.
- `api/` — endpoints: `provision.php`, `sync_ingest.php`, `import_token.php`, `check_provision.php`.
- `admin/` — páginas de administración (tenants, billing, ajustes).
- `packaging/` — `.iss` (Inno Setup) y scripts PowerShell para compilar el instalador.
- `scripts/` — utilidades de prueba y helpers (pruebas de provisión, init_sqlite, etc.).

---

## 🧭 Flujo de provisión

1. Compra / suscripción → `api/provision.php` crea `sync_token`, `allowed_host`, y un `provision_secret` de un solo uso.
2. Se envía un correo con `provisioning_url` que abre `provisioning.php` (muestra token + QR).
3. El cliente importa el token usando `api/import_token.php` y queda enlazado al tenant remoto.

---

## 📦 Empaquetado y creación del instalador

Comandos principales (desde PowerShell):

- Ejecutar build portable ZIP: revisar `packaging/` y ejecutar los scripts de build `.ps1`.
- Compilar Inno Setup (en host Windows con InnoSetup instalado): ejecutar `Packaging/SpaceParkInstaller.iss` desde los scripts de build. Nota: se ha usado `Compression=none` para evitar abortos en máquinas con recursos limitados.

---

## ✅ Pruebas y verificación

- Pruebas E2E: scripts en `scripts/` para validar provisión, import token y sincronización SQLite → MySQL.
- Ejecutar migraciones de desarrollo antes de pruebas: `php run_migration.php` o ejecutar los `.sql` en el entorno objetivo (ver `migrations/`).

---

## 🛠 Operaciones cotidianas (admin)

- Rotar/revocar `sync_token` desde `admin/tenants.php`.
- Reenviar link de provisión o regenerar `provision_secret` en caso de pérdida.
- Monitorear `sync_logs` y `outbox` para resolver conflictos.

---

## 📋 Consideraciones de seguridad

- Mantener el `sync_token` confidencial; rotarlo si se sospecha compromiso.
- `provision_secret` es de un solo uso y expira después de su consumo.
- Limitar `allowed_host` en tenants para evitar provisioning desde hosts no autorizados.

---

## 🧪 Recomendaciones y próximos pasos

- Agregar CI para ejecutar migraciones y pruebas E2E en cada PR.
- Implementar avisos de rotación de token en la UI y recordatorios automáticos.
- Mejorar la automatización para creación de PRs (ci/gh cli) si se requiere.

---

## 📞 Contacto / contribuciones

- Para contribuir: abrir PRs en `feature/*` o en `main` (si existe) y seguir las convenciones del repo.

---

_Archivo generado automáticamente y resumen de funcionalidades del proyecto._

© SpacePark - Documentación generada el 2026-02-02
