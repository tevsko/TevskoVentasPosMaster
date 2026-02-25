<?php
/**
 * API para gestionar settings generales (tabla settings)
 * Endpoint: admin/api/settings.php
 */

// Desactivar visualización de errores para no romper el JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../src/Database.php';
    require_once __DIR__ . '/../../src/Auth.php';

    $auth = new Auth();
    // Requerir rol de admin para este endpoint
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sesión expirada']);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION['user_id'];

    // Obtener rol (verificación extra)
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();

    if ($role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permiso denegado']);
        exit;
    }

    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'set':
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? '';
            
            if (empty($key)) {
                throw new Exception('Key requerida');
            }
            
            $driver = Database::getInstance()->getDriver();
            
            if ($driver === 'sqlite') {
                $stmt = $db->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            } else {
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) 
                                      VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Guardado'
            ]);
            break;
            
        case 'get':
            $key = $input['key'] ?? '';
            if (empty($key)) throw new Exception('Key requerida');
            
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $val = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'value' => $val ?: '0'
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
