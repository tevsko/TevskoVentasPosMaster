# Resumen de Funcionalidad - SpacePark POS

Este documento detalla la estructura y capacidades del sistema SpacePark, una solución de Punto de Venta (POS) diseñada para funcionar con sincronización Offline-First y gestión centralizada SaaS.

## 1. Arquitectura General
El sistema opera en un modelo híbrido:
- **Cliente (Local):** Aplicación POS que funciona en el escritorio (empaquetada con PHPDesktop) o en servidor local. Utiliza SQLite para operación offline.
- **Servidor (Nube/SaaS):** Panel de administración central y API de sincronización. Utiliza MySQL.
- **Sincronización:** Bidireccional. Los cambios locales se encolan (`sync_queue`) y se envían a la nube (`outbox`).

## 2. Módulos Principales

### A. Punto de Venta (POS) (`/pos`)
Interfaz principal para el cajero/usuario.
- **Ventas:** Registro de transacciones, cálculo de totales.
- **Corte de Caja:** Reportes de turno y cierre de caja.
- **Offline:** Capacidad de operar sin internet gracias a la base de datos local SQLite.

### B. Administración Central (`/admin`)
Panel de control para el administrador del SaaS.
- **Gestión de Tenants (Inquilinos):** Alta, baja y administración de clientes (sucursales).
- **Provisión:** Generación de tokens de sincronización y códigos QR para conectar nuevas terminales.
- **Suscripciones:** Integración preparada para cobros (ej. Mercado Pago).

### C. API y Sincronización (`/api`, `/src`)
El núcleo de la conectividad.
- **`TenantManager.php`:** Gestiona la identificación y validación de cada cliente.
- **`sync.php` / `sync_worker.php`:** Procesos en segundo plano que encargan de enviar y recibir datos.
- **Endpoints:**
    - `provision.php`: Inicia el registro de una nueva terminal.
    - `sync_ingest.php`: Recibe datos desde los clientes locales.
    - `import_token.php`: Vincula una instalación local con la nube.

### D. Instalación y Despliegue (`/install`, `/packaging`)
Herramientas para facilitar la puesta en marcha.
- **Instalador Web (`/install`):** Asistente paso a paso para configurar la base de datos y el primer usuario.
- **Empaquetado (`/packaging`):** Scripts de PowerShell e Inno Setup para crear un instalador `.exe` de Windows autonomo (sin necesitar instalar XAMPP aparte).

## 3. Estado de Limpieza
El proyecto ha sido auditado y limpiado:
- Se han eliminado scripts temporales y de depuración (`tmp_*`).
- Se mantiene la estructura esencial para operación y desarrollo.
- Listo para control de versiones en GitHub.
