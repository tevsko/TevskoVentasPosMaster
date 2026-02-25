<?php
/**
 * API para gestionar testimonios de clientes
 * Endpoint: admin/api/landing_testimonials.php
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
$action = $input['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ============================================
        // Listar todos los testimonios
        // ============================================
        case 'list':
            $stmt = $db->query("SELECT * FROM landing_testimonials ORDER BY display_order ASC");
            $testimonials = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'testimonials' => $testimonials
            ]);
            break;
        
        // ============================================
        // Obtener un testimonio específico
        // ============================================
        case 'get':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            $stmt = $db->prepare("SELECT * FROM landing_testimonials WHERE id = ?");
            $stmt->execute([$id]);
            $testimonial = $stmt->fetch();
            
            if (!$testimonial) {
                throw new Exception('Testimonio no encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'testimonial' => $testimonial
            ]);
            break;
        
        // ============================================
        // Crear nuevo testimonio
        // ============================================
        case 'create':
            $customerName = $input['customer_name'] ?? '';
            $businessName = $input['business_name'] ?? '';
            $testimonial = $input['testimonial'] ?? '';
            $rating = $input['rating'] ?? 5;
            $avatarUrl = $input['avatar_url'] ?? '';
            
            if (empty($customerName)) {
                throw new Exception('El nombre del cliente es requerido');
            }
            
            if (empty($testimonial)) {
                throw new Exception('El testimonio es requerido');
            }
            
            // Validar rating
            $rating = max(1, min(5, intval($rating)));
            
            // Obtener el siguiente orden
            $stmt = $db->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM landing_testimonials");
            $nextOrder = $stmt->fetch()['next_order'];
            
            $stmt = $db->prepare("
                INSERT INTO landing_testimonials 
                (customer_name, business_name, testimonial, rating, avatar_url, display_order, active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $customerName, $businessName, $testimonial, 
                $rating, $avatarUrl, $nextOrder
            ]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Testimonio creado correctamente',
                'id' => $newId
            ]);
            break;
        
        // ============================================
        // Actualizar testimonio existente
        // ============================================
        case 'update':
            $id = $input['id'] ?? 0;
            $customerName = $input['customer_name'] ?? '';
            $businessName = $input['business_name'] ?? '';
            $testimonial = $input['testimonial'] ?? '';
            $rating = $input['rating'] ?? 5;
            $avatarUrl = $input['avatar_url'] ?? '';
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            if (empty($customerName)) {
                throw new Exception('El nombre del cliente es requerido');
            }
            
            if (empty($testimonial)) {
                throw new Exception('El testimonio es requerido');
            }
            
            // Validar rating
            $rating = max(1, min(5, intval($rating)));
            
            $stmt = $db->prepare("
                UPDATE landing_testimonials 
                SET customer_name = ?, business_name = ?, testimonial = ?, 
                    rating = ?, avatar_url = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $customerName, $businessName, $testimonial,
                $rating, $avatarUrl, $id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Testimonio actualizado correctamente'
            ]);
            break;
        
        // ============================================
        // Eliminar testimonio
        // ============================================
        case 'delete':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            // Obtener avatar para eliminarlo si existe
            $stmt = $db->prepare("SELECT avatar_url FROM landing_testimonials WHERE id = ?");
            $stmt->execute([$id]);
            $testimonial = $stmt->fetch();
            
            if ($testimonial && $testimonial['avatar_url']) {
                $avatarPath = __DIR__ . '/../../' . $testimonial['avatar_url'];
                if (file_exists($avatarPath)) {
                    unlink($avatarPath);
                }
            }
            
            $stmt = $db->prepare("DELETE FROM landing_testimonials WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Testimonio eliminado correctamente'
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
            
            $stmt = $db->prepare("UPDATE landing_testimonials SET active = NOT active WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado'
            ]);
            break;
        
        // ============================================
        // Reordenar testimonios
        // ============================================
        case 'reorder':
            $order = $input['order'] ?? [];
            
            if (empty($order)) {
                throw new Exception('Orden requerido');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE landing_testimonials SET display_order = ? WHERE id = ?");
            
            foreach ($order as $index => $id) {
                $stmt->execute([$index + 1, $id]);
            }
            
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Orden actualizado correctamente'
            ]);
            break;
        
        // ============================================
        // Upload de avatar
        // ============================================
        case 'upload':
            if (!isset($_FILES['avatar'])) {
                throw new Exception('No se recibió ninguna imagen');
            }
            
            $file = $_FILES['avatar'];
            
            // Validar tipo de archivo
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP');
            }
            
            // Validar tamaño (1MB máximo para avatares)
            if ($file['size'] > 1 * 1024 * 1024) {
                throw new Exception('El archivo es muy grande. Máximo 1MB');
            }
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = __DIR__ . '/../../assets/uploads/testimonials/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Avatar subido correctamente',
                'url' => 'assets/uploads/testimonials/' . $filename,
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
