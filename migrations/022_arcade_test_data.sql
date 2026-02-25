-- =====================================================
-- Datos de Prueba para Módulo Móvil
-- =====================================================
-- INSTRUCCIONES:
-- 1. Abrir phpMyAdmin
-- 2. Seleccionar la base de datos
-- 3. Ir a la pestaña "SQL"
-- 4. Copiar y pegar este contenido
-- 5. Hacer clic en "Continuar"
-- =====================================================

-- IMPORTANTE: Cambiar el tenant_id según tu base de datos
-- Para encontrar tu tenant_id, ejecuta: SELECT id, company_name FROM tenants;
SET @tenant_id = 1; -- CAMBIAR ESTE VALOR

-- 1. Crear un local de arcade
INSERT INTO arcade_locations (tenant_id, location_name, address, active)
VALUES (@tenant_id, 'Arcade Central', 'Av. Principal 123', 1);

SET @location_id = LAST_INSERT_ID();

-- 2. Crear productos (fichas)
INSERT INTO arcade_products (location_id, product_name, price, active, display_order)
VALUES 
  (@location_id, 'Ficha $100', 100.00, 1, 1),
  (@location_id, 'Ficha $200', 200.00, 1, 2),
  (@location_id, 'Ficha $500', 500.00, 1, 3);

-- 3. Crear empleado
-- Usuario: empleado1
-- Contraseña: 123456
-- Password hash generado con: password_hash('123456', PASSWORD_DEFAULT)
INSERT INTO arcade_employees (location_id, username, password_hash, full_name, daily_salary, active)
VALUES (
  @location_id, 
  'empleado1', 
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
  'Juan Pérez', 
  20000.00, 
  1
);

-- =====================================================
-- VERIFICACIÓN
-- =====================================================

SELECT 'Datos insertados correctamente' AS status;

SELECT 
  l.id AS location_id,
  l.location_name,
  COUNT(DISTINCT p.id) AS productos,
  COUNT(DISTINCT e.id) AS empleados
FROM arcade_locations l
LEFT JOIN arcade_products p ON l.id = p.location_id
LEFT JOIN arcade_employees e ON l.id = e.location_id
WHERE l.tenant_id = @tenant_id
GROUP BY l.id;

SELECT 
  e.id,
  e.username,
  e.full_name,
  e.daily_salary,
  l.location_name
FROM arcade_employees e
INNER JOIN arcade_locations l ON e.location_id = l.id
WHERE l.tenant_id = @tenant_id;
