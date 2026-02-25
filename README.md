<div align="center">

<img src="assets/img/favicon_astronaut.png" alt="SpacePark Logo" width="120"/>

# 🚀 SpacePark — Tevsko Ventas POS Master

**Plataforma SaaS de Punto de Venta Multi-Tenant para negocios físicos**

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-orange?logo=mysql)](https://mysql.com)
[![SQLite](https://img.shields.io/badge/SQLite-Offline--First-green?logo=sqlite)](https://sqlite.org)
[![License](https://img.shields.io/badge/License-Privado-red)](LICENSE)
[![PWA](https://img.shields.io/badge/PWA-SpacePark%20Ventas-blueviolet?logo=pwa)](mobile/)

</div>

---

## 📋 Tabla de Contenidos

- [¿Qué es SpacePark?](#-qué-es-spacepark)
- [Arquitectura del Sistema](#-arquitectura-del-sistema)
- [Módulos del Sistema](#-módulos-del-sistema)
  - [Super Admin (SaaS Owner)](#-super-admin-saas-owner)
  - [Dashboard de Cliente](#-dashboard-de-cliente-dueño-del-negocio)
  - [Punto de Venta (POS)](#-punto-de-venta-pos)
  - [Módulo Mobile (PWA)](#-módulo-mobile-pwa--spacepark-ventas)
  - [Landing Page & CMS](#-landing-page--cms)
  - [Sistema de Licencias](#-sistema-de-licencias)
  - [Sincronización Cloud](#-sincronización-cloud)
- [Estructura de Archivos](#-estructura-de-archivos)
- [Stack Tecnológico](#-stack-tecnológico)
- [Integraciones de Pago](#-integraciones-de-pago)
- [Seguridad](#-seguridad)
- [Instalación](#-instalación)
- [Deployment (Hosting Web)](#-deployment-hosting-web)
- [Instalación en Windows (POS Desktop)](#-instalación-en-windows-pos-desktop)
- [Recuperación de Contraseña](#-recuperación-de-contraseña)
- [Expansiones Futuras](#-expansiones-futuras)

---

## 🌐 ¿Qué es SpacePark?

SpacePark es una plataforma **SaaS multi-tenant** de Punto de Venta (POS) desarrollada con PHP/MySQL, diseñada para operar tanto en la nube como sin internet (**offline-first**). Sus principales características son:

- **Multi-Tenant**: Un solo servidor puede manejar múltiples negocios completamente aislados entre sí.
- **Offline-First**: El POS local funciona con SQLite sin necesidad de internet. Las ventas se sincronizan automáticamente a la nube cuando detecta conexión.
- **Modelo SaaS**: El operador del sistema (SuperAdmin) puede vender el acceso a múltiples dueños de negocio (Tenants), cobra licencias mensuales y gestiona todo desde un panel centralizado.
- **PWA Móvil**: Los empleados de campo pueden registrar ventas y fotos de cierres de caja desde su celular.

---

## 🏗️ Arquitectura del Sistema

```
┌────────────────────────────────────────────────────────────────────┐
│                        NUBE (MySQL + PHP)                          │
│                                                                    │
│  ┌─────────────────┐   ┌─────────────────┐   ┌─────────────────┐  │
│  │  Super Admin    │   │  Tenant Admin   │   │  PWA Móvil      │  │
│  │  (SaaS Owner)   │   │  (Cliente)      │   │  (Empleados)    │  │
│  └────────┬────────┘   └────────┬────────┘   └────────┬────────┘  │
│           │                    │                      │            │
│           └────────────────────┴──────────────────────┘            │
│                                │                                   │
│                     ┌──────────▼──────────┐                        │
│                     │     MySQL DB        │                        │
│                     │  (Multi-Tenant)     │                        │
│                     └──────────┬──────────┘                        │
└────────────────────────────────┼───────────────────────────────────┘
                                 │ Sincronización Bidireccional
                      ┌──────────▼──────────┐
                      │  POS LOCAL Windows  │
                      │  PHPDesktop + SQLite│
                      │  (Funciona Offline) │
                      └─────────────────────┘
```

---

## 🔧 Módulos del Sistema

---

### 👑 Super Admin (SaaS Owner)

Panel exclusivo para el operador de la plataforma. Accesible en `/admin/dashboard.php`.

| Función | Descripción |
|---|---|
| **Gestión de Tenants** | Crear, pausar y eliminar cuentas de clientes |
| **Control de Licencias** | Asignar módulos, fechas de vencimiento y días de gracia |
| **Planes SaaS** | Definir planes (Mensual, Trimestral, Anual) con módulos opcionales |
| **Precios de Módulos** | Configurar precios individuales por módulo |
| **Backups & Sync** | Monitorear y forzar sincronizaciones |
| **Gestión Global de Empleados** | Ver empleados de todos los tenants |
| **Gestión de Máquinas (Arcade)** | Catálogo global de máquinas de juego |
| **Reportes Globales** | Ventas y estadísticas de todos los clientes |
| **Editor de Landing Page** | CMS visual con preview en tiempo real |
| **Analytics** | Estadísticas de visitas a la landing page |
| **Configuración de SMTP** | Email de notificaciones (PHPMailer) |
| **Integración Mercado Pago** | Tokens para cobrar planes y para los POS de clientes |
| **Integración MODO** | Configuración de billetera digital |
| **Modo Mantenimiento** | Activar/desactivar la landing page públicamente |
| **Mi Perfil** | Ver/editar datos personales y cambiar contraseña |

---

### 👤 Dashboard de Cliente (Dueño del Negocio)

Panel para el dueño del negocio que contrató SpacePark. Accesible bajo su subdominio.

| Función | Descripción |
|---|---|
| **Mi Sucursal** | Dashboard con ventas del día, totales y gráficos |
| **Multi-Sucursales** | Gestionar múltiples locales físicos en una sola cuenta |
| **Productos y Categorías** | Alta, modificación y baja de productos con precios dinámicos |
| **Gestión de Empleados** | Crear usuarios con roles (admin, empleado) y salarios diarios |
| **Reportes de Ventas** | Estadísticas por producto, por empleado y por fecha |
| **Módulo Arcade** | Sub-módulo especializado para negocios de arcade/juegos |
| **Reportes Arcade Diarios** | Con fotos de cierres de caja enviadas desde el celular |
| **Mi Licencia** | Ver estado del plan, fechas de vencimiento y módulos activos |
| **Renovación Online** | Pago de renovación de licencia con Mercado Pago |
| **Descargas** | Descarga del instalador de POS para Windows |
| **Mi Perfil** | Editar datos y cambiar contraseña |

---

### 🛒 Punto de Venta (POS)

El corazón de la operación diaria. Disponible en `/pos/index.php`.

#### Funcionalidades de Venta
- **Interfaz Táctil Optimizada**: Diseñada para tablets y pantallas táctiles en mostrador
- **Búsqueda Rápida de Productos**: Por nombre o código, con resultados en tiempo real
- **Selector de Cantidad**: Modal numérico con controles `+/-` y teclado numérico
- **Carrito Dinámico**: Agregar, quitar y modificar cantidades antes de confirmar

#### Métodos de Pago
| Método | Descripción |
|---|---|
| **Efectivo** | Calcula el vuelto automáticamente |
| **Tarjeta** | Registro de pagos con tarjeta (sin procesador externo) |
| **Mercado Pago QR** | Genera QR dinámico usando el Access Token del cliente |
| **MODO** | Integración con billetera digital argentina |
| **Mixto** | Combinación de múltiples métodos en una sola venta |

#### Gestión de Caja
- Apertura de turno con monto inicial declarado
- Registro de ingresos y egresos de caja
- Reporte de cierre de turno con resumen completo
- Historial de todas las ventas del turno

#### Modo Offline
- Funciona completamente sin internet con **SQLite local**
- Sincronización automática en background cada 5 minutos cuando detecta conexión
- Cola de sincronización: ninguna venta se pierde

---

### 📱 Módulo Mobile (PWA — SpacePark Ventas)

Aplicación web progresiva para empleados del salón. Accesible en `/mobile/`.

#### Características del PWA
- **Instalable**: Se instala como app nativa en Android/iOS desde el navegador
- **Service Worker**: Cachea recursos para funcionamiento offline básico
- **Ícono de Astronauta** con branding SpacePark
- **Diseño Mobile-First**: Optimizado para pantallas pequeñas

#### Flujo de Trabajo del Empleado
1. **Login**: El empleado se autentica con sus credenciales del sistema
2. **Dashboard Daily**: Ve un resumen del día: ventas, retiros, saldo esperado en caja
3. **Cargar Reporte Diario**: Ingresa la cantidad vendida de cada producto
4. **Captura de Evidencia**: Toma una foto obligatoria del cierre físico de caja
5. **Retiro de Sueldo**: Registra cuánto dinero retiró de la caja como pago diario
6. **Envío**: Envía el reporte al servidor para que el administrador lo vea

#### Control de Caja Automático
El sistema calcula el efectivo esperado en caja:
```
Efectivo Esperado = Ingresos por Ventas - Retiros de Sueldo - Gastos Registrados
```

#### Reporte Diario para el Admin
El administrador ve en su panel:
- Detalle de ventas por producto ingresado por cada empleado
- Foto del cierre de caja (evidencia)
- Comparativo: Efectivo esperado vs. Efectivo informado
- Estado del reporte: Pendiente / Enviado / Verificado

---

### 🌍 Landing Page & CMS

Página pública para captar nuevos clientes, con un sistema CMS integrado.

#### Secciones de la Landing
- **Hero**: Título, subtítulo y CTA configurables
- **Planes y Precios**: Tabla de precios con módulos por plan
- **Características**: Cards con funcionalidades del sistema
- **Testimonios**: Clientes destacados
- **Formulario de Registro**: Alta de nuevos clientes directamente desde la landing

#### CMS (Editor Visual)
- Editor de texto en vivo con **preview en tiempo real**
- Configuración de colores y estilos
- Activación/desactivación de secciones
- **Modo Mantenimiento**: Toggle para mostrar página "En Construcción" a visitantes sin bloquear acceso al admin

#### Analytics de Landing
- Registro de visitas por página
- Conteos de clics en botones de CTA
- Tráfico histórico con gráficos

---

### 🔑 Sistema de Licencias

Sistema propio de gestión de licencias SaaS.

#### Para el SuperAdmin
- Asignar licencias con fecha de vencimiento por módulo
- Configurar días de gracia post-vencimiento
- Sincronización automática del estado de licencias desde la nube al POS local
- Notificaciones de vencimiento próximo

#### Para el Cliente
- Ver estado de todos sus módulos (POS, Mobile, MP, MODO, Cloud, Arcade)
- Botón de renovación online integrado
- Historial de pagos de licencias

#### Módulos Licenciables
| Módulo | Descripción |
|---|---|
| **Base** | Dashboard de cliente |
| **POS** | Acceso al sistema de punto de venta |
| **Cloud Sync** | Sincronización entre POS local y nube |
| **Mercado Pago** | Cobros QR en el POS |
| **MODO** | Billetera digital en el POS |
| **Arcade/Mobile** | Módulo de arcade y PWA móvil |

---

### ☁️ Sincronización Cloud

Sistema de sincronización bidireccional entre el POS local (SQLite) y el servidor central (MySQL).

#### Flujo de Sincronización (Push - Local → Nube)
1. Cada venta o cambio de producto genera una entrada en `sync_queue`
2. Cada 5 minutos (background automático) o manualmente, el `sync_upload.php` envía la cola al servidor
3. El servidor `api/sync_ingest.php` recibe, valida el token y aplica los cambios
4. El log de sincronización registra el resultado (éxito/error)

#### Flujo de Sincronización (Pull - Nube → Local)
1. El `sync_pull.php` descarga productos y configuraciones del servidor
2. Aplica cambios localmente sin duplicar registros
3. El POS refleja inmediatamente los nuevos precios o productos

#### Seguridad de Sincronización
- Token único por dispositivo cliente
- Validación de Tenant ID en cada request
- Imposible que un tenant vea datos de otro

---

## 🗂️ Estructura de Archivos

```
SpaceParkMaster/
│
├── 📁 admin/               # Panel de administración
│   ├── dashboard.php       # Dashboard principal
│   ├── tenants.php         # Gestión de clientes SaaS
│   ├── branches.php        # Gestión de sucursales
│   ├── employees.php       # Gestión de empleados
│   ├── machines.php        # Máquinas de arcade
│   ├── reports.php         # Reportes de ventas
│   ├── licenses.php        # Gestión de licencias
│   ├── plans_manage.php    # Planes SaaS
│   ├── module_prices.php   # Precios por módulo
│   ├── settings.php        # Config. SMTP, MP, MODO, Cloud
│   ├── landing_editor.php  # CMS de landing page
│   ├── landing_analytics.php # Analytics de visitas
│   ├── profile.php         # Perfil de usuario
│   ├── branch_view.php     # Vista del tenant (su sucursal)
│   ├── license.php         # Vista de licencia del cliente
│   ├── downloads.php       # Descarga del instalador
│   ├── arcade_*.php        # Módulo arcade (5 archivos)
│   ├── layout_head.php     # Layout header común
│   └── layout_foot.php     # Layout footer común
│
├── 📁 api/                 # Endpoints REST internos
│   ├── sync_ingest.php     # Recibe datos del POS local
│   ├── sync_pull.php       # Envía datos al POS local
│   ├── create_payment_preference.php  # MP checkout
│   ├── mp_webhook_license.php  # Webhook de MP
│   ├── check_license_status.php  # Estado de licencia
│   ├── register_device.php # Registro de dispositivos
│   ├── provision.php       # Aprovisionar nuevo tenant
│   ├── process_signup.php  # Alta de nuevo cliente
│   ├── test_smtp.php       # Test de SMTP
│   └── mobile/             # Endpoints para la PWA
│
├── 📁 pos/                 # Punto de Venta
│   ├── index.php           # Interfaz principal del POS
│   └── licenses.php        # Gestión de licencias local
│
├── 📁 mobile/              # PWA Móvil (SpacePark Ventas)
│   ├── index.html          # App móvil (SPA)
│   ├── report.html         # Vista de reporte diario
│   ├── manifest.json       # Manifest PWA
│   ├── sw.js               # Service Worker
│   ├── css/                # Estilos
│   └── js/                 # Lógica de la app
│
├── 📁 src/                 # Clases PHP core
│   ├── Auth.php            # Autenticación y sesiones
│   └── Database.php        # Conexión y migraciones DB
│
├── 📁 scripts/             # Workers de background
│   ├── sync_upload.php     # Worker: Local → Nube
│   └── sync_pull.php       # Worker: Nube → Local
│
├── 📁 migrations/          # Scripts SQL de migración
├── 📁 config/              # Configuración DB (gitignored)
├── 📁 docs/                # Documentación técnica
├── 📁 assets/              # CSS, JS, imágenes
├── 📁 packaging/           # Scripts para generar instalador Windows
│
├── login.php               # Login unificado
├── forgot_password.php     # Recuperación de contraseña
├── reset_password.php      # Restablecer contraseña
├── signup.php              # Registro de nuevos clientes
├── landing.php             # Landing page pública
├── maintenance.html        # Página de mantenimiento
├── index.php               # Entry point (redirecciona)
├── logout.php              # Cierre de sesión
├── provisioning.php        # Aprovisionamiento automático
└── sync_worker.php         # Worker de sincronización
```

---

## ⚙️ Stack Tecnológico

| Capa | Tecnología |
|---|---|
| **Backend** | PHP 8.3+ |
| **Base de datos Nube** | MySQL 8.0+ |
| **Base de datos Local** | SQLite (vía PDO) |
| **Frontend** | HTML5, CSS3, JavaScript (ES5/ES6 compatible) |
| **UI Framework** | Bootstrap 5.3 + Bootstrap Icons |
| **Email** | PHPMailer (SMTP) |
| **PWA** | Service Worker + Web Manifest |
| **Empaquetado Windows** | PHPDesktop + Inno Setup |
| **Servidor Recomendado** | cPanel (Apache/LiteSpeed) |

---

## 💳 Integraciones de Pago

### Mercado Pago
- **QR en POS**: Genera preferencias de pago y QR en tiempo real usando la API de MP
- **Cobro de Planes SaaS**: El SuperAdmin cobra los planes de los clientes con su propio Access Token
- **Webhook**: Recibe notificaciones de pago para activar licencias automáticamente

### MODO
- Integración con billetera digital MODO para pagos QR alternativos en el POS
- Activación por credenciales (Client ID, Client Secret, Store ID)

---

## 🔒 Seguridad

| Medida | Implementación |
|---|---|
| **Aislamiento Multi-Tenant** | Cada query valida `tenant_id` para que un cliente nunca vea datos de otro |
| **Hashing de Contraseñas** | `password_hash()` con bcrypt (PHP nativo) |
| **Tokens de Sincronización** | UUID único por cliente, validado en cada request de sincronización |
| **Sesiones Seguras** | `session_regenerate_id(true)` en cada login |
| **Recuperación de Contraseña** | Token de 64 bytes hexadecimales con expiración de 1 hora |
| **Control de Roles** | Verificación de rol en cada página admin (`requireRole(['admin', 'branch_manager'])`) |
| **Modo Mantenimiento** | Solo admins pueden acceder durante mantenimiento |
| **Protección CSRF** | Validación de acción en formularios sensibles |

---

## 🚀 Instalación

### Requisitos del Servidor
- PHP 8.0 o superior
- MySQL 5.7 / 8.0
- Extensiones PHP: `pdo`, `pdo_mysql`, `pdo_sqlite`, `curl`, `gd`, `mbstring`
- Composer (para PHPMailer)

### Pasos de Instalación en Hosting (cPanel)

1. **Subir archivos** vía FTP o File Manager a `public_html/`
2. **Crear base de datos MySQL** en cPanel
3. **Crear el archivo `config/db.php`**:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'tu_base_de_datos');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_DRIVER', 'mysql');
```
4. **Instalar dependencias** (si hay acceso SSH):
```bash
composer install
```
5. **Ejecutar migración** en phpMyAdmin:
```sql
-- Ejecutar el contenido de migrations/add_password_reset_tokens.sql
```
6. **Crear usuario administrador** inicial en phpMyAdmin:
```sql
INSERT INTO users (id, username, password_hash, role) 
VALUES ('admin-001', 'admin', '$2y$10$...', 'admin');
```

---

## 🌐 Deployment (Hosting Web)

### Archivos Críticos para Subir

```
/ (raíz)
├── login.php
├── forgot_password.php
├── reset_password.php
├── signup.php
├── landing.php
├── index.php
├── logout.php
├── .htaccess
├── config/db.php         ← Configurar con datos del hosting
├── admin/
├── api/
├── mobile/
├── pos/
├── src/
├── scripts/
├── assets/
└── vendor/               ← Instalar con composer install
```

> [!IMPORTANT]
> **Nunca subir**: `config/db.php` con datos de producción al repositorio. El archivo ya está en `.gitignore`.

---

## 💻 Instalación en Windows (POS Desktop)

El POS puede ejecutarse como aplicación Windows standalone usando PHPDesktop.

### Generar el Instalador
```bash
# En Windows, ejecutar:
.\packaging\build_installer.ps1
```

Esto genera un archivo `.exe` con:
- PHPDesktop (servidor interno + Chromium)
- PHP + extensiones empaquetadas
- VC++ Runtime incluido
- Instalador Inno Setup

### Proceso de Instalación en el Cliente
1. Ejecutar el `.exe` en la PC del cliente
2. Abrir `http://localhost:8080`
3. El asistente (`setup_client.php`) pide el **Token de Sincronización** provisto por el SuperAdmin
4. El POS queda listo para operar offline

---

## 🔐 Recuperación de Contraseña

> Solo para administradores y dueños de negocio. Los empleados no tienen recuperación autónoma.

1. Ir a `/login.php` → clic en **"¿Olvidaste tu contraseña?"**
2. Ingresar el email registrado en la cuenta
3. Recibir email con link seguro (expira en 1 hora)
4. Ingresar nueva contraseña desde el link recibido

> En instalaciones **locales (SQLite)**, la opción no aparece. El admin debe resetear la contraseña manualmente desde la base de datos.

---

## 🔮 Expansiones Futuras

### Sistema de Tarjetas RFID para Arcade (ESP32)
Plan completo documentado en [`docs/arcade_cards_expansion.md`](docs/arcade_cards_expansion.md).

- **Hardware**: ESP32 + Lector RFID RC522 + Relé + Display OLED
- **Pairing**: Cada lector tiene un ID de 4 caracteres, vinculado a una máquina desde el panel
- **Multi-Lector por Máquina**: Soporte para máquinas dobles (2-4 lectores en la misma máquina)
- **Precios Dinámicos**: El ESP32 no tiene el precio grabado; lo obtiene del servidor en cada uso
- **Offline-First**: Opera en la red WiFi local del negocio

### Pagos QR en Máquinas de Arcade
- Display OLED muestra QR dinámico para pagos por Mercado Pago / transferencia
- El cliente paga desde su celular sin necesidad de tarjeta física

---

## 📄 Licencia

Este software es **propietario y privado**. Desarrollado por **Tevsko** para uso comercial exclusivo.

Para consultas de licenciamiento: [tevsko.com.ar](https://tevsko.com.ar)

---

<div align="center">

© 2026 **SpacePark** — Tevsko Ventas POS Master

*Hecho con 🚀 en Argentina*

</div>
