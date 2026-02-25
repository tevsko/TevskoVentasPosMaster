<?php
// api/get_module_prices.php
// API para obtener precios de módulos

header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener todos los precios activos
    $stmt = $db->query("
        SELECT 
            module_code,
            module_name,
            description,
            monthly_price,
            quarterly_price,
            annual_price,
            display_order
        FROM module_prices 
        WHERE active = 1 
        ORDER BY display_order ASC
    ");
    
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear precios
    foreach ($prices as &$price) {
        $price['monthly_price'] = (float) $price['monthly_price'];
        $price['quarterly_price'] = $price['quarterly_price'] ? (float) $price['quarterly_price'] : null;
        $price['annual_price'] = $price['annual_price'] ? (float) $price['annual_price'] : null;
        
        // Calcular descuentos
        if ($price['quarterly_price']) {
            $quarterly_discount = round((1 - ($price['quarterly_price'] / ($price['monthly_price'] * 3))) * 100);
            $price['quarterly_discount'] = $quarterly_discount . '%';
        }
        
        if ($price['annual_price']) {
            $annual_discount = round((1 - ($price['annual_price'] / ($price['monthly_price'] * 12))) * 100);
            $price['annual_discount'] = $annual_discount . '%';
        }
    }
    
    echo json_encode([
        'success' => true,
        'prices' => $prices
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener precios: ' . $e->getMessage()
    ]);
}
