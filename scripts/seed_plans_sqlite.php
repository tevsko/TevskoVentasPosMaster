<?php
require_once __DIR__ . '/../src/Database.php';
$db = Database::getInstance()->getConnection();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver !== 'sqlite') {
    echo "Not running: DB is not sqlite.\n"; exit;
}
$plans = [
    ['starter_monthly', 'Starter (Monthly)', 9.99, 'monthly', json_encode(['Soporte básico','1 Sucursal'])],
    ['starter_quarterly', 'Starter (Quarterly)', 27.99, 'quarterly', json_encode(['Soporte básico','1 Sucursal'])],
    ['starter_annual', 'Starter (Annual)', 99.99, 'annual', json_encode(['Soporte básico','1 Sucursal'])]
];
foreach ($plans as $p) {
    $stmt = $db->prepare('INSERT OR IGNORE INTO plans (code, name, price, period, features, active) VALUES (?, ?, ?, ?, ?, 1)');
    $stmt->execute($p);
}
echo "Seeded plans.\n";