# Guía de Despliegue en CDMON - SpacePark Master

Esta guía paso a paso te ayudará a desplegar tu sistema SpacePark en un hosting compartido como **cdmon** utilizando la carpeta `release_web` generada.

## 1. Requisitos del Hosting (CDMON)
Asegúrate de que tu plan de hosting cumpla con lo siguiente (estándar en cdmon):
- **PHP:** Versión 8.0, 8.1 u 8.2 (Recomendado 8.1+).
- **Base de Datos:** MySQL o MariaDB.
- **Acceso:** Panel de control (para crear BD) y FTP/Gestor de Archivos (para subir ficheros).
- **SSL:** Activado (HTTPS) para mayor seguridad.

## 2. Preparación de Archivos
Antes de subir nada, asegúrate de tener la versión más limpia de tu proyecto.
1.  En tu computadora local, busca el archivo `generar_release.bat` que creamos.
2.  Haz doble clic en él.
3.  Espera a que termine. Se creará/actualizará la carpeta **`release_web`** en tu escritorio (dentro de la carpeta del proyecto).
    > Esta es la ÚNICA carpeta cuyo contenido debes subir.

## 3. Configuración de Base de Datos (en CDMON)
1.  Ingresa a tu panel de control de cdmon.
2.  Ve a la sección de **Bases de Datos MySQL**.
3.  **Crear nueva base de datos**:
    - Nombre de la base: `tuusuario_spacepark` (ejemplo).
    - Usuario: `tuusuario_admin` (anótalo).
    - Contraseña: Crea una contraseña fuerte (anótala).
4.  **Importante:** No necesitas importar las tablas manualmente. El sistema tiene un **instalador automático**.

## 4. Subida de Archivos
1.  Abre tu cliente FTP (FileZilla) o usa el Gestor de Archivos Web de cdmon.
2.  Navega a la carpeta pública de tu dominio (usualmente `/web`, `/public_html` o `/www`).
3.  **Sube todo el contenido** que está DENTRO de la carpeta `release_web`.
    - *Nota:* Asegúrate de subir también el archivo `.htaccess` (a veces está oculto).

## 5. Instalación Automática
Una vez subidos los archivos:
1.  Abre tu navegador y entra a tu dominio: `https://tudominio.com/install/`
    - Si subiste los archivos en una subcarpeta, sería `https://tudominio.com/carpeta/install/`
2.  Verás la pantalla **"Instalador SpacePark"**.
3.  Completa los datos con la información del paso 3:
    - **Host:** Generalmente es `localhost` (o la IP que te indique cdmon).
    - **Usuario BD:** El que creaste (`tuusuario_admin`).
    - **Contraseña BD:** La que definiste.
    - **Nombre BD:** El nombre completo (`tuusuario_spacepark`).
4.  Define tu usuario Administrador General (SaaS Admin):
    - Usuario: `admin` (o el que quieras).
    - Contraseña: `admin123` (¡Cámbiala!).
5.  Haz clic en **"Instalar Sistema"**.

> Si todo sale bien, verás un mensaje de éxito y un botón para ir al Login.

## 6. Verificación Post-Instalación
1.  Intenta iniciar sesión en `https://tudominio.com/login.php`.
2.  Una vez dentro, ve al panel de administración.
3.  **Seguridad:** Por seguridad, se recomienda borrar o renombrar la carpeta `/install` de tu servidor usando el FTP una vez finalizada la instalación.

## Solución de Problemas Comunes
- **Error 500:** Revisa si tu hosting soporta las reglas de compresión GZIP en el archivo `.htaccess`. Si falla, puedes comentar las líneas de `<IfModule mod_deflate.c>` poniendo un `#` al inicio.
- **Error de Conexión BD:** Verifica usuario y contraseña en el archivo `config/db.php` (si el instalador falló en crearlo, puedes editarlo manualmente).
