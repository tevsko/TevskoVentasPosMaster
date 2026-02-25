<?php
/**
 * Helper functions para la Landing Page
 * Proporciona funciones para cargar contenido dinámico desde el CMS
 */

class LandingHelpers {
    private static $db;
    private static $settings = null;
    
    /**
     * Inicializar con la conexión a la base de datos
     */
    public static function init($db) {
        self::$db = $db;
    }
    
    /**
     * Obtener todos los settings (con caché en memoria)
     * @return array Asociativo con key => value
     */
    public static function getSettings() {
        if (self::$settings === null) {
            $stmt = self::$db->query("SELECT setting_key, setting_value FROM landing_settings");
            self::$settings = [];
            while ($row = $stmt->fetch()) {
                self::$settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        return self::$settings;
    }
    
    /**
     * Obtener un setting específico
     * @param string $key Clave del setting
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor del setting
     */
    public static function getSetting($key, $default = '') {
        $settings = self::getSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Obtener slides del carousel activos
     * @return array Lista de slides ordenados
     */
    public static function getActiveCarousel() {
        $stmt = self::$db->query("
            SELECT * FROM landing_carousel 
            WHERE active = 1 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener características activas
     * @return array Lista de features ordenadas
     */
    public static function getActiveFeatures() {
        $stmt = self::$db->query("
            SELECT * FROM landing_features 
            WHERE active = 1 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener testimonios activos
     * @return array Lista de testimonios ordenados
     */
    public static function getActiveTestimonials() {
        $stmt = self::$db->query("
            SELECT * FROM landing_testimonials 
            WHERE active = 1 
            ORDER BY display_order ASC
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Registrar una visita en el contador diario
     */
    public static function registerVisit() {
        try {
            $today = date('Y-m-d');
            $stmt = self::$db->prepare("
                INSERT INTO landing_visits (visit_date, visit_count) 
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE visit_count = visit_count + 1
            ");
            $stmt->execute([$today]);
        } catch (Exception $e) {
            // Silenciar errores para no afectar la carga de la página
            error_log("Error registrando visita: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener total de visitas (últimos 30 días)
     * @return int Total de visitas
     */
    public static function getTotalVisits() {
        try {
            $stmt = self::$db->query("
                SELECT SUM(visit_count) as total 
                FROM landing_visits 
                WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ");
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
