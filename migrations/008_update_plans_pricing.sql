-- Migration: 008_update_plans_pricing.sql
-- Actualiza los planes de suscripción con nueva estructura de precios
-- Planes: Mensual (básico), Trimestral, Anual

-- Limpiar planes existentes (comentar si quieres mantener histórico)
-- DELETE FROM plans WHERE code IN ('starter_monthly', 'starter_quarterly', 'starter_annual');

-- Plan Mensual (Básico)
-- 1 POS incluido, Mercado Pago incluido, MODO no disponible
INSERT INTO plans (
    code, name, price, period, 
    pos_limit, pos_extra_fee,
    allow_mp_integration, mp_fee,
    allow_modo_integration, modo_fee,
    features, active
) VALUES (
    'basic_monthly',
    'Plan Mensual Básico',
    5000.00,
    'monthly',
    1,  -- 1 POS incluido
    1500.00,  -- Costo por POS adicional mensual
    1,  -- Permite MP
    0.00,  -- MP incluido (sin costo adicional)
    0,  -- NO permite MODO
    0.00,
    JSON_ARRAY('1 POS Incluido', 'Mercado Pago Incluido', 'Sincronización Nube', 'Soporte Básico'),
    1
) ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    price = VALUES(price), 
    period = VALUES(period),
    pos_limit = VALUES(pos_limit),
    pos_extra_fee = VALUES(pos_extra_fee),
    allow_mp_integration = VALUES(allow_mp_integration),
    mp_fee = VALUES(mp_fee),
    allow_modo_integration = VALUES(allow_modo_integration),
    modo_fee = VALUES(modo_fee),
    features = VALUES(features),
    active = VALUES(active);

-- Plan Trimestral
-- 2 POS incluidos, MP y MODO opcionales con costo adicional
INSERT INTO plans (
    code, name, price, period, 
    pos_limit, pos_extra_fee,
    allow_mp_integration, mp_fee,
    allow_modo_integration, modo_fee,
    features, active
) VALUES (
    'standard_quarterly',
    'Plan Trimestral',
    15000.00,
    'quarterly',
    2,  -- 2 POS incluidos
    2000.00,  -- Costo por POS adicional trimestral
    1,  -- Permite MP
    3000.00,  -- Costo adicional MP trimestral
    1,  -- Permite MODO
    3000.00,  -- Costo adicional MODO trimestral
    JSON_ARRAY('2 POS Incluidos', 'Sincronización Nube', 'Soporte Técnico', 'Integraciones Opcionales'),
    1
) ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    price = VALUES(price), 
    period = VALUES(period),
    pos_limit = VALUES(pos_limit),
    pos_extra_fee = VALUES(pos_extra_fee),
    allow_mp_integration = VALUES(allow_mp_integration),
    mp_fee = VALUES(mp_fee),
    allow_modo_integration = VALUES(allow_modo_integration),
    modo_fee = VALUES(modo_fee),
    features = VALUES(features),
    active = VALUES(active);

-- Plan Anual
-- 2 POS incluidos, MP y MODO opcionales con costo adicional
-- 20% de ahorro vs plan trimestral
INSERT INTO plans (
    code, name, price, period,
    pos_limit, pos_extra_fee,
    allow_mp_integration, mp_fee,
    allow_modo_integration, modo_fee,
    features, active
) VALUES (
    'standard_annual',
    'Plan Anual',
    50000.00,
    'annual',
    2,  -- 2 POS incluidos
    6000.00,  -- Costo por POS adicional anual
    1,  -- Permite MP
    8000.00,  -- Costo adicional MP anual
    1,  -- Permite MODO
    8000.00,  -- Costo adicional MODO anual
    JSON_ARRAY('2 POS Incluidos', 'Sincronización Nube', 'Soporte Prioritario', 'Integraciones Opcionales', '20% Descuento'),
    1
) ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    price = VALUES(price), 
    period = VALUES(period),
    pos_limit = VALUES(pos_limit),
    pos_extra_fee = VALUES(pos_extra_fee),
    allow_mp_integration = VALUES(allow_mp_integration),
    mp_fee = VALUES(mp_fee),
    allow_modo_integration = VALUES(allow_modo_integration),
    modo_fee = VALUES(modo_fee),
    features = VALUES(features),
    active = VALUES(active);

-- Verificación
SELECT id, code, name, price, period, pos_limit, pos_extra_fee, mp_fee, modo_fee 
FROM plans 
WHERE code IN ('basic_monthly', 'standard_quarterly', 'standard_annual')
ORDER BY 
    CASE period 
        WHEN 'monthly' THEN 1 
        WHEN 'quarterly' THEN 2 
        WHEN 'annual' THEN 3 
    END;
