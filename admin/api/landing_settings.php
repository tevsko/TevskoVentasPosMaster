<?php
/**
 * API para gestionar configuración de la landing page
 * Endpoint: admin/api/landing_settings.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';

// Verificar autenticación
$auth = new Auth();
try {
    $auth->requireRole(['admin']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Obtener datos del request
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================
        // Actualizar un setting individual
        // ============================================
        case 'update':
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? '';
            
            if (empty($key)) {
                throw new Exception('Key requerida');
            }
            
            $stmt = $db->prepare("
                UPDATE landing_settings 
                SET setting_value = ? 
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, $key]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Setting actualizado'
            ]);
            break;
        
        // ============================================
        // Guardar múltiples settings
        // ============================================
        case 'save_all':
            $settings = $input['settings'] ?? [];
            
            if (empty($settings)) {
                throw new Exception('No hay settings para guardar');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE landing_settings 
                SET setting_value = ? 
                WHERE setting_key = ?
            ");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$value, $key]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Configuración guardada correctamente',
                'count' => count($settings)
            ]);
            break;
        
        // ============================================
        // Obtener todos los settings
        // ============================================
        case 'get_all':
            $stmt = $db->query("SELECT setting_key, setting_value, setting_type FROM landing_settings");
            $settings = [];
            
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = [
                    'value' => $row['setting_value'],
                    'type' => $row['setting_type']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;
        
        // ============================================
        // Obtener un setting específico
        // ============================================
        case 'get':
            $key = $input['key'] ?? '';
            
            if (empty($key)) {
                throw new Exception('Key requerida');
            }
            
            $stmt = $db->prepare("
                SELECT setting_value, setting_type 
                FROM landing_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            
            if (!$row) {
                throw new Exception('Setting no encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'value' => $row['setting_value'],
                'type' => $row['setting_type']
            ]);
            break;
        
        // ============================================
        // Upload de imagen para popup
        // ============================================
        case 'upload_popup_image':
            if (!isset($_FILES['image'])) {
                throw new Exception('No se recibió ninguna imagen');
            }
            
            $file = $_FILES['image'];
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP');
            }
            
            // Validar tamaño (2MB máximo)
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('El archivo es muy grande. Máximo 2MB');
            }
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'popup_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = __DIR__ . '/../../assets/uploads/popup/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Imagen subida correctamente',
                'url' => 'assets/uploads/popup/' . $filename,
                'filename' => $filename
            ]);
            break;
        
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
