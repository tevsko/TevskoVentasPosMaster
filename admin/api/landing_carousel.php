<?php
/**
 * API para gestionar slides del carousel
 * Endpoint: admin/api/landing_carousel.php
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
        // Listar todos los slides
        // ============================================
        case 'list':
            $stmt = $db->query("SELECT * FROM landing_carousel ORDER BY display_order ASC");
            $slides = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'slides' => $slides
            ]);
            break;
        
        // ============================================
        // Obtener un slide específico
        // ============================================
        case 'get':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            $stmt = $db->prepare("SELECT * FROM landing_carousel WHERE id = ?");
            $stmt->execute([$id]);
            $slide = $stmt->fetch();
            
            if (!$slide) {
                throw new Exception('Slide no encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'slide' => $slide
            ]);
            break;
        
        // ============================================
        // Crear nuevo slide
        // ============================================
        case 'create':
            $title = $input['title'] ?? '';
            $subtitle = $input['subtitle'] ?? '';
            $bgType = $input['background_type'] ?? 'gradient';
            $gradientStart = $input['gradient_start'] ?? '#1e3c72';
            $gradientEnd = $input['gradient_end'] ?? '#2a5298';
            $imageUrl = $input['image_url'] ?? '';
            $icon = $input['icon'] ?? 'bi-star';
            $buttonText = $input['button_text'] ?? '';
            $buttonLink = $input['button_link'] ?? '';
            
            if (empty($title)) {
                throw new Exception('El título es requerido');
            }
            
            // Obtener el siguiente orden
            $stmt = $db->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM landing_carousel");
            $nextOrder = $stmt->fetch()['next_order'];
            
            $stmt = $db->prepare("
                INSERT INTO landing_carousel 
                (title, subtitle, background_type, gradient_start, gradient_end, image_url, icon, button_text, button_link, display_order, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $title, $subtitle, $bgType, $gradientStart, $gradientEnd, 
                $imageUrl, $icon, $buttonText, $buttonLink, $nextOrder
            ]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Slide creado correctamente',
                'id' => $newId
            ]);
            break;
        
        // ============================================
        // Actualizar slide existente
        // ============================================
        case 'update':
            $id = $input['id'] ?? 0;
            $title = $input['title'] ?? '';
            $subtitle = $input['subtitle'] ?? '';
            $bgType = $input['background_type'] ?? 'gradient';
            $gradientStart = $input['gradient_start'] ?? '#1e3c72';
            $gradientEnd = $input['gradient_end'] ?? '#2a5298';
            $imageUrl = $input['image_url'] ?? '';
            $icon = $input['icon'] ?? 'bi-star';
            $buttonText = $input['button_text'] ?? '';
            $buttonLink = $input['button_link'] ?? '';
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            if (empty($title)) {
                throw new Exception('El título es requerido');
            }
            
            $stmt = $db->prepare("
                UPDATE landing_carousel 
                SET title = ?, subtitle = ?, background_type = ?, 
                    gradient_start = ?, gradient_end = ?, image_url = ?,
                    icon = ?, button_text = ?, button_link = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $title, $subtitle, $bgType, $gradientStart, $gradientEnd,
                $imageUrl, $icon, $buttonText, $buttonLink, $id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Slide actualizado correctamente'
            ]);
            break;
        
        // ============================================
        // Eliminar slide
        // ============================================
        case 'delete':
            $id = $input['id'] ?? 0;
            
            if (!$id) {
                throw new Exception('ID requerido');
            }
            
            // Obtener imagen para eliminarla si existe
            $stmt = $db->prepare("SELECT image_url FROM landing_carousel WHERE id = ?");
            $stmt->execute([$id]);
            $slide = $stmt->fetch();
            
            if ($slide && $slide['image_url']) {
                $imagePath = __DIR__ . '/../../' . $slide['image_url'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $stmt = $db->prepare("DELETE FROM landing_carousel WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Slide eliminado correctamente'
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
            
            $stmt = $db->prepare("UPDATE landing_carousel SET active = NOT active WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado'
            ]);
            break;
        
        // ============================================
        // Reordenar slides
        // ============================================
        case 'reorder':
            $order = $input['order'] ?? [];
            
            if (empty($order)) {
                throw new Exception('Orden requerido');
            }
            
            $db->beginTransaction();
            
            $stmt = $db->prepare("UPDATE landing_carousel SET display_order = ? WHERE id = ?");
            
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
        // Upload de imagen
        // ============================================
        case 'upload':
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
            $filename = 'carousel_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = __DIR__ . '/../../assets/uploads/carousel/' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Error al subir el archivo');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Imagen subida correctamente',
                'url' => 'assets/uploads/carousel/' . $filename,
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
