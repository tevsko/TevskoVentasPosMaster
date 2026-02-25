-- Migration 020: Landing Page CMS
-- Crea todas las tablas necesarias para el sistema de gestión de contenido de la landing page

-- =====================================================
-- Tabla: landing_carousel
-- Gestión de slides del carousel principal
-- =====================================================
CREATE TABLE IF NOT EXISTS landing_carousel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL COMMENT 'Título principal del slide',
    subtitle TEXT COMMENT 'Subtítulo o descripción',
    background_type ENUM('gradient', 'image') DEFAULT 'gradient' COMMENT 'Tipo de fondo',
    gradient_start VARCHAR(7) COMMENT 'Color inicial del gradiente (#RRGGBB)',
    gradient_end VARCHAR(7) COMMENT 'Color final del gradiente (#RRGGBB)',
    image_url VARCHAR(255) COMMENT 'URL de la imagen de fondo',
    icon VARCHAR(100) COMMENT 'Clase de icono Bootstrap (ej: bi-shop-window)',
    button_text VARCHAR(100) COMMENT 'Texto del botón CTA (opcional)',
    button_link VARCHAR(255) COMMENT 'Link del botón CTA (opcional)',
    display_order INT DEFAULT 0 COMMENT 'Orden de visualización',
    active TINYINT(1) DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: landing_features
-- Características/beneficios del producto
-- =====================================================
CREATE TABLE IF NOT EXISTS landing_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(100) NOT NULL COMMENT 'Clase de icono Bootstrap',
    title VARCHAR(150) NOT NULL COMMENT 'Título de la característica',
    description TEXT COMMENT 'Descripción detallada',
    display_order INT DEFAULT 0 COMMENT 'Orden de visualización',
    active TINYINT(1) DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: landing_testimonials
-- Testimonios de clientes
-- =====================================================
CREATE TABLE IF NOT EXISTS landing_testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL COMMENT 'Nombre del cliente',
    business_name VARCHAR(150) COMMENT 'Nombre del negocio (opcional)',
    testimonial TEXT NOT NULL COMMENT 'Texto del testimonio',
    rating INT DEFAULT 5 COMMENT 'Calificación 1-5 estrellas',
    avatar_url VARCHAR(255) COMMENT 'URL del avatar (opcional)',
    display_order INT DEFAULT 0 COMMENT 'Orden de visualización',
    active TINYINT(1) DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: landing_settings
-- Configuración general de la landing
-- =====================================================
CREATE TABLE IF NOT EXISTS landing_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL COMMENT 'Clave del setting',
    setting_value TEXT COMMENT 'Valor del setting',
    setting_type ENUM('text', 'textarea', 'url', 'color', 'boolean', 'json', 'html') DEFAULT 'text' COMMENT 'Tipo de dato',
    description VARCHAR(255) COMMENT 'Descripción del setting',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Tabla: landing_visits
-- Contador básico de visitas (analytics simple)
-- =====================================================
CREATE TABLE IF NOT EXISTS landing_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL COMMENT 'Fecha de la visita',
    visit_count INT DEFAULT 1 COMMENT 'Número de visitas en ese día',
    UNIQUE KEY unique_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATOS INICIALES: Carousel
-- Migrar contenido actual de landing.php
-- =====================================================
INSERT INTO landing_carousel (title, subtitle, background_type, gradient_start, gradient_end, icon, button_text, button_link, display_order, active) VALUES
('Tu Negocio, Optimizado', 'El sistema de Punto de Venta más veloz y moderno del mercado.', 'gradient', '#1e3c72', '#2a5298', 'bi-shop-window', 'Ver Planes', '#pricing', 1, 1),
('Cobros Integrados', 'Acepta Mercado Pago con QR dinámico integrado automáticamente.', 'gradient', '#009ee3', '#003366', 'bi-qr-code-scan', 'Más Información', '#features', 2, 1),
('Control Total en la Nube', 'Monitorea tus sucursales y ventas desde cualquier lugar del mundo.', 'gradient', '#8e44ad', '#3498db', 'bi-cloud-check-fill', 'Comenzar Ahora', '#pricing', 3, 1);

-- =====================================================
-- DATOS INICIALES: Features
-- Características principales del producto
-- =====================================================
INSERT INTO landing_features (icon, title, description, display_order, active) VALUES
('bi-lightning-charge', 'Rápido y Eficiente', 'Interfaz optimizada para ventas rápidas. Procesa transacciones en segundos.', 1, 1),
('bi-qr-code-scan', 'Mercado Pago Integrado', 'Genera QR dinámicos automáticamente. Cobra con Mercado Pago sin complicaciones.', 2, 1),
('bi-cloud-arrow-up', 'Sincronización en la Nube', 'Tus datos siempre seguros y accesibles desde cualquier dispositivo.', 3, 1),
('bi-graph-up-arrow', 'Reportes Detallados', 'Analiza tus ventas con gráficos y reportes en tiempo real.', 4, 1),
('bi-people-fill', 'Multi-Usuario', 'Gestiona múltiples usuarios con diferentes roles y permisos.', 5, 1),
('bi-shield-check', 'Seguro y Confiable', 'Tus datos protegidos con encriptación y backups automáticos.', 6, 1);

-- =====================================================
-- DATOS INICIALES: Testimonials
-- Ejemplos de testimonios (pueden ser editados/eliminados)
-- =====================================================
INSERT INTO landing_testimonials (customer_name, business_name, testimonial, rating, display_order, active) VALUES
('María González', 'Kiosco Central', 'SpacePark transformó mi negocio. Ahora puedo aceptar pagos con QR y llevar un control exacto de mis ventas. ¡Excelente!', 5, 1, 1),
('Carlos Rodríguez', 'Almacén Don Carlos', 'La sincronización en la nube es increíble. Puedo ver mis ventas desde casa en tiempo real. Muy recomendado.', 5, 2, 1),
('Laura Martínez', 'Librería Escolar', 'Fácil de usar y muy completo. El soporte técnico es excelente. Mis empleados lo aprendieron en minutos.', 5, 3, 1);

-- =====================================================
-- DATOS INICIALES: Settings
-- Configuración general de la landing
-- =====================================================
INSERT INTO landing_settings (setting_key, setting_value, setting_type, description) VALUES
-- WhatsApp
('whatsapp_enabled', '1', 'boolean', 'Mostrar botón flotante de WhatsApp'),
('whatsapp_number', '541135508224', 'text', 'Número de WhatsApp con código de país'),
('whatsapp_message', 'Hola! Quiero información sobre SpacePark POS', 'text', 'Mensaje predeterminado de WhatsApp'),

-- Popup Promocional
('popup_enabled', '0', 'boolean', 'Mostrar popup promocional'),
('popup_title', '¡Oferta Especial!', 'text', 'Título del popup'),
('popup_content', '<h4>Descuento del 20% en tu primer mes</h4><p>Contrata SpacePark POS hoy y obtén un <strong>20% de descuento</strong> en tu primer mes de suscripción.</p><p>¡Oferta válida por tiempo limitado!</p>', 'html', 'Contenido HTML del popup'),
('popup_image', '', 'url', 'URL de imagen del popup (opcional)'),
('popup_frequency', 'once_per_session', 'text', 'Frecuencia: always, once_per_session, once_per_day'),

-- Testimonios
('testimonials_enabled', '1', 'boolean', 'Mostrar sección de testimonios'),
('testimonials_title', 'Lo que dicen nuestros clientes', 'text', 'Título de la sección de testimonios'),

-- Redes Sociales
('facebook_url', '', 'url', 'URL de página de Facebook'),
('instagram_url', '', 'url', 'URL de perfil de Instagram'),
('twitter_url', '', 'url', 'URL de perfil de Twitter/X'),
('linkedin_url', '', 'url', 'URL de perfil de LinkedIn'),
('youtube_url', '', 'url', 'URL de canal de YouTube'),

-- SEO
('meta_title', 'SpacePark - Tu Solución de Punto de Venta', 'text', 'Meta título para SEO'),
('meta_description', 'Sistema de punto de venta moderno con integración de Mercado Pago, sincronización en la nube y reportes en tiempo real. Ideal para kioscos, almacenes y comercios.', 'textarea', 'Meta descripción para SEO'),
('meta_keywords', 'punto de venta, pos, mercado pago, qr, sistema de ventas, spacepark', 'text', 'Meta keywords para SEO'),

-- Analytics
('analytics_enabled', '1', 'boolean', 'Habilitar contador de visitas básico'),
('google_analytics_id', '', 'text', 'ID de Google Analytics (opcional, ej: G-XXXXXXXXXX)'),

-- Hero Section
('hero_title', 'SpacePark POS', 'text', 'Título principal de la landing'),
('hero_subtitle', 'La solución completa para tu negocio', 'text', 'Subtítulo principal'),

-- Features Section
('features_title', 'Características Principales', 'text', 'Título de la sección de características'),
('features_subtitle', 'Todo lo que necesitas para gestionar tu negocio', 'text', 'Subtítulo de características');

-- =====================================================
-- FIN DE LA MIGRACIÓN
-- =====================================================
