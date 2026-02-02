# Packaging - PHPDesktop + SQLite (skeleton)

This document describes how to create a distributable Windows app using PHPDesktop with the SpacePark codebase.

1. Download PHPDesktop for Windows (https://github.com/cztomczak/phpdesktop/releases) - choose the Chromium+PHP bundle matching PHP 8.3.
2. Place the contents of the PHPDesktop folder into a packaging workspace.
3. Copy the entire project webroot (the repository folder) into `phpdesktop/www/`.
4. Configure `settings.json` in PHPDesktop to set `server = {"document_root": "www", "router": "index.php"}` or similar.
5. Ensure `config/db.php` in the packaged app sets `DB_DRIVER = 'sqlite'` and `DB_SQLITE_FILE = __DIR__ + '/data/data.sqlite'` (the installer should set this automatically).
6. Include `php` extensions required (`pdo_sqlite`) in the PHPDesktop build.
7. Add a post-install script that:
   - Creates `data` and `logs` directories.
   - Runs `php scripts/init_sqlite.php` to initialize schema.
   - Registers a short-cut and optionally schedules the `sync_worker.php` via Task Scheduler to run every minute (or run a background process inside PHPDesktop).
8. Build an installer using Inno Setup or NSIS that copies files, sets ACLs, and creates shortcuts.

Packaging helper files and a sample Inno Setup script are available in `packaging/`.

Quick build example (Windows):
- Ensure Inno Setup is installed (default: `C:\Program Files (x86)\Inno Setup 6`) and `ISCC.exe` is available.
- Download PHPDesktop (Chromium+PHP matching PHP 8.3) and provide its path to `packaging\build_installer.ps1`.
- Run in PowerShell (as Administrator): `cd packaging; .\build_installer.ps1`

Testing:
- Install on a fresh Windows VM and verify the first run creates `data/data.sqlite` and that `landing.php` loads.
- Simulate a purchase to verify provisioning and email outbox.

Post-provision step (token import)
- After provisioning, you will receive a `sync_token` for the tenant (from the provisioning API or email). The provisioning email contains a one-time **Provisioning URL** that opens a page with the token and a QR for quick import.
- The client (local install) should store that token in `settings.sync_api_token`. You can do this manually via the admin UI (Billing form), scan the QR from the provisioning page, or programmatically by POSTing to `api/import_token.php` with `sync_token` and optional `allowed_host`.
- Admins can manage (rotate/revoke) per-tenant tokens at Admin → Tenants; rotating sends an email to the tenant admin with the new token.
- The sync worker uses the local `sync_api_token` to authenticate to the cloud ingest endpoint. The cloud verifies the token against the tenant record and (optionally) verifies the request Host matches the tenant's `allowed_host`.


Notes:
- For automatic updates, implement an update check that downloads diffs and restarts the app.
- Consider bundling the worker as a service in the future for more robust background processing.

