# 🚀 SpacePark Master: Guía Integral del Sistema

SpacePark Master es una plataforma **SaaS (Software as a Service)** de Punto de Venta (POS) diseñada para operar tanto en la nube como en entornos locales de baja conectividad. Su arquitectura *offline-first* y su sistema de sincronización bidireccional lo hacen ideal para negocios con múltiples sucursales y movilidad constante.

---

## 🏗️ Arquitectura del Sistema

La plataforma se divide en tres capas principales:
1.  **Nube (Central)**: Servidor MySQL que aloja todos los datos de todos los clientes (Tenants), las licencias y el panel administrativo global.
2.  **Dashboard de Cliente**: Interfaz web para que el dueño del negocio gestione sus sucursales, productos y vea reportes.
3.  **POS Local (Desktop)**: Aplicación Windows (empaquetada con PHPDesktop + SQLite) que funciona sin internet y sincroniza las ventas cuando detecta conexión.
4.  **Módulo Mobile (PWA)**: Aplicación web progresiva diseñada para empleados que deben reportar ventas diarias y capturar fotos de cierres de caja manuscritos.

---

## 👑 Funciones del Administrador General (SaaS SuperAdmin)

Diseñado para el dueño de la plataforma que revende el software.

-   **Gestión de Tenants (Clientes)**: Crear, pausar o eliminar cuentas de empresas clientes.
-   **Control de Licencias**: Configurar planes (Mensual, Trimestral, Anual), precios y días de gracia.
-   **Monitoreo de Sincronización**: Ver el estado de conexión de todos los clientes y resolver conflictos de datos.
-   **Gestión de Pagos**: Panel para ver las suscripciones pagadas y pendientes mediante Mercado Pago.
-   **CMS de Landing Page**: Modificar textos, precios y secciones de la página principal (`landing.php`) directamente desde el panel sin tocar código.
-   **Configuración Global**: Ajustes de SMTP para correos, tokens de API centralizados y logs del sistema.

---

## 👤 Funciones del Cliente (Dueño del Negocio / Comercio)

Para quien contrata SpacePark para gestionar sus tiendas.

-   **Multi-Sucursales**: Gestionar múltiples locales físicos bajo una misma cuenta.
-   **Gestión de Productos**: Crear categorías, productos con precios dinámicos y control de stock básico.
-   **Gestión de Empleados**: Crear usuarios con roles específicos y asignarles sueldos diarios fijos o variables.
-   **Reportes de Ventas**: Visualizar estadísticas por sucursal, por empleado y por fecha. Exportación a Excel/PDF.
-   **Módulo de Arcade (Especializado)**: Visualización detallada de reportes diarios enviados desde el celular, incluyendo fotos de las planillas físicas.
-   **Renovación de Licencia**: Sistema de facturación propio para ver cuándo vence su plan y pagar la renovación directamente desde su panel.

---

## 🛒 Punto de Venta (POS)

El corazón de la operación diaria.

-   **Venta Rápida**: Interfaz optimizada para pantallas táctiles y teclado.
-   **Múltiples Métodos de Pago**: Soporte para Efectivo, Tarjeta (integración visual), Mercado Pago y **MODO** (QR).
-   **Cierres de Caja por Turno**: Control detallado de entradas y salidas de efectivo.
-   **Modo Offline**: Si cae el internet, el POS sigue vendiendo. Al volver la conexión, el `sync_worker` envía automáticamente las ventas acumuladas a la nube.

---

## 📱 Módulo Mobile (SpacePark Ventas)

Aplicación PWA para el personal operativo.

-   **Acceso Fácil**: Instalable como aplicación en el escritorio del celular desde el navegador.
-   **Reporte Diario Simplificado**: Los empleados cargan cuántos productos vendieron al final del día.
-   **Captura de Evidencia**: Obligación de tomar una foto al reporte físico o a la caja cerrada antes de enviar.
-   **Retiro de Sueldo**: Los empleados pueden reportar cuánto dinero retiraron de la caja para su paga diaria.
-   **Control de Caja**: El sistema calcula cuánto efectivo "debe" haber en el local basándose en las ventas reportadas, los gastos y los retiros de sueldo.

---

## 🛠️ Especificaciones Técnicas

-   **Lenguajes**: PHP 8.3+, JavaScript (Legacy Compatible 1.8), HTML5/CSS3.
-   **Bases de Datos**: MySQL (Nube) y SQLite (Local).
-   **Integraciones**: API de Mercado Pago, Sistema de Mailing PHP (PHPMailer).
-   **Empaquetado**: Inno Setup + PHPDesktop para distribución en Windows sin instalaciones complejas.
-   **Seguridad**: Aislamiento de datos por Tenant ID, Tokens de sincronización únicos y validación de sesiones JWT-Style.

---

© 2026 SpacePark - Tevsko Ventas POS Master.
