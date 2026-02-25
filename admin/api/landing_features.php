<?php
/**
 * API para gestionar características del producto
 * Endpoint: admin/api/landing_features.php
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
        // Listar todas las características
        // ============================================
        case 'list':
            $stmt = $db->query("SELECT * FROM landing_features ORDER BY display_order ASC");
            $features = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'features' => $features
            ]);
            break;
        
        // ============================================
        // Obtener una característica específica
        // ============================================
        case 'get':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            $stmt = $db->prepare("SELECT * FROM landing_features WHERE id = ?");
            $stmt->execute([$id]);
            $feature = $stmt->fetch();
            
            if (!$feature) {
                throw new Exception('Característica no encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'feature' => $feature
            ]);
            break;
        
        // ============================================
        // Crear nueva característica
        // ============================================
        case 'create':
            $icon = $input['icon'] ?? '';
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            
            if (empty($icon)) {
                throw new Exception('El icono es requerido');
            }
            
            if (empty($title)) {
                throw new Exception('El título es requerido');
            }
            
            // Obtener el siguiente orden
            $stmt = $db->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM landing_features");
            $nextOrder = $stmt->fetch()['next_order'];
            
            $stmt = $db->prepare("
                INSERT INTO landing_features 
                (icon, title, description, display_order, active)
                VALUES (?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$icon, $title, $description, $nextOrder]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Característica creada correctamente',
                'id' => $newId
            ]);
            break;
        
        // ============================================
        // Actualizar característica existente
        // ============================================
        case 'update':
            $id = $input['id'] ?? 0;
            $icon = $input['icon'] ?? '';
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            if (empty($icon)) {
                throw new Exception('El icono es requerido');
            }
            
            if (empty($title)) {
                throw new Exception('El título es requerido');
            }
            
            $stmt = $db->prepare("
                UPDATE landing_features 
                SET icon = ?, title = ?, description = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$icon, $title, $description, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Característica actualizada correctamente'
            ]);
            break;
        
        // ============================================
        // Eliminar característica
        // ============================================
        case 'delete':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            $stmt = $db->prepare("DELETE FROM landing_features WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Característica eliminada correctamente'
            ]);
            break;
        
        // ============================================
        // Toggle activo/inactivo
        // ============================================
        case 'toggle':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            $stmt = $db->prepare("UPDATE landing_features SET active = NOT active WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado'
            ]);
            break;
        
        // ============================================
        // Reordenar características
        // ============================================
        case 'reorder':
            $order = $input['order'] ?? [];
            
            if (empty($order)) {
                throw new Exception('Orden requerido');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE landing_features SET display_order = ? WHERE id = ?");
            
            foreach ($order as $index => $id) {
                $stmt->execute([$index + 1, $id]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Orden actualizado correctamente'
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
