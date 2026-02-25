<?php
// pos/index.php (V5: Original Success UI)
// CRITICAL: Load error handler FIRST to prevent headers errors
require_once __DIR__ . '/../bootstrap_error_handler.php';

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Uuid.php';

$auth = new Auth();
$auth->requireRole('employee');
$currentUser = $auth->getCurrentUser();
$branch_id = $currentUser['branch_id'];

$db = Database::getInstance()->getConnection();

// --- CONFIG & LICENSING ---
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch();

if (!$branch) die("Error: Sucursal no identificada.");

// Check POS License
$is_pos_licensed = (!$branch['license_pos_expiry'] || $branch['license_pos_expiry'] >= date('Y-m-d'));
if (!$is_pos_licensed) {
    // Mostrar pantalla de licencia vencida con opción de cerrar sesión
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Licencia Vencida - SpacePark</title>
        <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
        <style>
            body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
            .license-expired-card { background: white; padding: 3rem; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; max-width: 450px; }
            .license-expired-card h1 { color: #dc3545; font-size: 2.5rem; margin-bottom: 1rem; }
            .license-expired-card p { color: #6c757d; margin-bottom: 2rem; }
            .btn-logout { background: #6c757d; color: white; border: none; padding: 12px 30px; border-radius: 25px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
            .btn-logout:hover { background: #5a6268; color: white; }
        </style>
    </head>
    <body>
        <div class="license-expired-card">
            <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 4rem;"></i>
            <h1>Licencia Vencida</h1>
            <p>El módulo POS de su sucursal ha expirado.<br>Contacte al administrador para renovar la licencia.</p>
            <a href="../logout.php" class="btn btn-logout"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// MP Config
$mp_active = ($branch['mp_status'] == 1 && $branch['license_mp_expiry'] >= date('Y-m-d'));
$pos_title = $branch['pos_title'] ?? 'SpacePark POS';
$mp_collector_id = $branch['mp_collector_id'] ?? '';
$mp_country = 'MLA'; // Argentina por defecto

// --- LOGIC: CART & CHECKOUT ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_item') {
            $machine_id = strtoupper($_POST['machine_id']);
            $qty = (int)($_POST['quantity'] ?? 1);
            if ($qty < 1) $qty = 1; 
            
            $stmt = $db->prepare("SELECT * FROM machines WHERE id = ? AND active = 1");
            $stmt->execute([$machine_id]);
            $machine = $stmt->fetch();
            
            if ($machine) {
                // En cliente SQLite (single-branch), no validamos branch_id
                $driver = Database::getInstance()->getDriver();
                $is_wrong_branch = ($driver !== 'sqlite' && $machine['branch_id'] && $machine['branch_id'] !== $branch_id);
                
                if ($is_wrong_branch) {
                    $error = "Producto de otro local.";
                } else {
                    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['id'] === $machine['id']) {
                            $item['qty'] += $qty;
                            $item['total'] = $item['price'] * $item['qty'];
                            $found = true; break;
                        }
                    }
                    if (!$found) {
                        $_SESSION['cart'][] = [
                            'id' => $machine['id'], 'name' => $machine['name'],
                            'price' => $machine['price'], 'qty' => $qty,
                            'total' => $machine['price'] * $qty
                        ];
                    }
                }
            } else { $error = "Producto no encontrado."; }
        }
        elseif ($_POST['action'] === 'clear_cart') { $_SESSION['cart'] = []; }
        elseif ($_POST['action'] === 'checkout') {
            $method = $_POST['payment_method'];
            $amount_paid = (float)($_POST['amount_paid'] ?? 0);
            $cart = $_SESSION['cart'] ?? [];
            $cart_total = array_sum(array_column($cart, 'total'));
            
            if ($method === 'cash' && $amount_paid == 0) $amount_paid = $cart_total;
            
            if (empty($cart)) $error = "Carrito vacío.";
            elseif ($method === 'cash' && $amount_paid < $cart_total) $error = "Pago insuficiente.";
            else {
                try {
                    $db->beginTransaction();
                    $total_txn = 0;

                    $insertSaleStmt = $db->prepare("INSERT INTO sales (id, user_id, branch_id, machine_id, amount, payment_method, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insertQueueStmt = $db->prepare("INSERT INTO sync_queue (resource_type, resource_uuid, payload, attempts, next_attempt, created_at) VALUES ('sale', ?, ?, 0, " . \Database::nowSql() . ", " . \Database::nowSql() . ")");

                    foreach ($cart as $item) {
                        $saleId = Uuid::generate();
                        $createdAt = date('Y-m-d H:i:s');

                        $insertSaleStmt->execute([$saleId, $currentUser['id'], $branch_id, $item['id'], $item['total'], $method, $createdAt]);

                        $payload = json_encode([
                            'id' => $saleId,
                            'user_id' => $currentUser['id'],
                            'branch_id' => $branch_id,
                            'machine_id' => $item['id'],
                            'amount' => $item['total'],
                            'payment_method' => $method,
                            'created_at' => $createdAt
                        ]);

                        $insertQueueStmt->execute([$saleId, $payload]);

                        $total_txn += $item['total'];
                    }

                    $db->commit();
                    $_SESSION['last_total'] = $total_txn;
                    $_SESSION['last_paid'] = ($method === 'qr') ? $total_txn : $amount_paid;
                    $_SESSION['last_change'] = ($method === 'qr') ? 0 : ($amount_paid - $total_txn);
                    $_SESSION['cart'] = [];
                    header("Location: index.php"); exit;
                } catch (Exception $e) { $db->rollBack(); $error = $e->getMessage(); }
            }
        }
    }
}

// Data Handling
$cart = $_SESSION['cart'] ?? [];
$cart_total = array_sum(array_column($cart, 'total'));

// Load Machines
// En cliente offline, mostramos todos los productos activos (single-branch)
// En servidor multi-tenant, filtramos por branch_id
$driver = Database::getInstance()->getDriver();
if ($driver === 'sqlite') {
    // Cliente: mostrar todos los productos activos
    $sqlMachines = "SELECT id, name, price FROM machines WHERE active=1";
} else {
    // Servidor: filtrar por sucursal
    $sqlMachines = "SELECT id, name, price FROM machines WHERE active=1 AND (branch_id IS NULL OR branch_id='$branch_id')";
}
$machines = $db->query($sqlMachines)->fetchAll(PDO::FETCH_ASSOC);

// Shift Logic
$cur_h = (int)date('H');
$shift_date = ($cur_h < 9) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$shift_start = $shift_date . ' 09:00:00';
$shift_end   = date('Y-m-d H:i:s'); // Current time instead of future timestamp
$stmt = $db->prepare("SELECT COUNT(*) as total_txns, COALESCE(SUM(amount),0) as total_amount, COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END),0) as total_cash, COALESCE(SUM(CASE WHEN payment_method = 'qr' THEN amount ELSE 0 END),0) as total_qr FROM sales WHERE user_id = ? AND created_at >= ? AND created_at <= ?");
$stmt->execute([$currentUser['id'], $shift_start, $shift_end]);
$my_shift = $stmt->fetch();

// Flash Logic
$show_success = isset($_SESSION['last_total']);
$last_total_display = $_SESSION['last_total'] ?? 0;
$last_change = $_SESSION['last_change'] ?? -1;

if ($show_success) { unset($_SESSION['last_total']); unset($_SESSION['last_paid']); unset($_SESSION['last_change']); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pos_title) ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="../assets/img/favicon_astronaut.png">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --header-bg: #4a90e2; 
            --bg-color: #f4f8fb;
        }
        body { 
            background: var(--bg-color);
            font-family: 'Open Sans', 'Segoe UI', sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* --- HEADER --- */
        .pos-header {
            background: var(--header-bg);
            color: white;
            padding: 8px 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .user-tag { background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 15px; font-weight: 600; font-size: 0.9rem; }

        /* --- LAYOUT --- */
        .pos-container {
            flex: 1;
            display: flex;
            overflow: hidden;
            padding: 10px;
            gap: 15px;
        }

        /* LEFT */
        .left-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
            border-radius: 10px;
        }
        
        .search-area { background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .main-input { border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1.1rem; padding: 10px; font-weight: bold; color: #333; }
        .main-input:focus { border-color: var(--header-bg); box-shadow: none; }

        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 12px;
            padding-bottom: 20px;
        }
        .product-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-bottom: 4px solid #e2e8f0; 
            border-radius: 8px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.1s;
            height: 100%;
            display: flex; flex-direction: column; justify-content: center;
        }
        .product-card:active { transform: translateY(2px); border-bottom-width: 1px; margin-top: 3px; }
        .p-name { font-weight: 700; color: #2c3e50; line-height: 1.2; font-size: 0.95rem; margin-bottom: 5px; }
        .p-price { font-weight: 800; color: var(--header-bg); font-size: 1.1rem; }

        /* RIGHT */
        .right-panel {
            flex: 1;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            max-width: 380px;
            min-width: 320px;
            overflow: hidden;
        }

        .cart-header {
            background: #f8fafc;
            padding: 10px 15px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center;
        }

        .cart-items { flex: 1; overflow-y: auto; background: white; }
        .cart-row {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
        }
        .cart-row:nth-child(even) { background: #fdfdfd; }
        
        .totals-block { background: #f1f5f9; padding: 15px; text-align: right; }
        .total-label { font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: bold; }
        .total-val { font-size: 2.2rem; font-weight: 800; color: #2c3e50; line-height: 1; }

        /* NUMPAD */
        .numpad-container {
            background: #e2e8f0;
            padding: 10px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .np-btn {
            background: white;
            border: none;
            border-radius: 6px;
            font-size: 1.4rem;
            font-weight: bold;
            color: #475569;
            padding: 12px 0;
            box-shadow: 0 2px 0 #cbd5e1;
            transition: all 0.1s;
        }
        .np-btn:active { transform: translateY(2px); box-shadow: none; }
        
        .np-pay { grid-column: span 3; background: #22c55e; color: white; box-shadow: 0 3px 0 #15803d; text-transform: uppercase; font-size: 1.1rem; letter-spacing: 1px; }
        .np-pay:active { background: #16a34a; }
        
        .np-clear { background: #ef4444; color: white; box-shadow: 0 2px 0 #b91c1c; font-size: 1rem; display: flex; align-items: center; justify-content: center; }

        /* QTY MODAL */
        .qty-controls { display: flex; align-items: center; justify-content: center; gap: 15px; margin: 15px 0; }
        .qty-btn { width: 50px; height: 50px; border-radius: 50%; font-size: 1.5rem; font-weight: bold; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .qty-minus { background: #fee2e2; color: #dc2626; }
        .qty-plus { background: #dbf6e5; color: #16a34a; }
        
        /* SUCCESS OVERLAY - ORIGINAL STYLE RESTORED */
        .success-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #198754;
            z-index: 9999; display: flex; justify-content: center; align-items: center; flex-direction: column; color: white;
            animation: fadeIn 0.2s; cursor: pointer;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* MOBILE */
        @media (max-width: 768px) {
            .pos-container { flex-direction: column; padding: 5px; }
            .right-panel { flex: none; max-width: none; }
            .cart-items { max-height: 150px; }
            .numpad-container { gap: 5px; }
            .np-btn { padding: 15px 0; } 
        }
    </style>
</head>
<body>

    <nav class="pos-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span class="fs-5 fw-bold"><?= htmlspecialchars($pos_title) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="user-tag"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($currentUser['username']) ?></span>
            <button class="btn btn-sm btn-link text-white p-0 ms-2" data-bs-toggle="modal" data-bs-target="#shiftModal"><i class="bi bi-info-circle fs-5"></i></button>
            <a href="../logout.php" class="btn btn-sm btn-link text-white p-0 ms-2"><i class="bi bi-box-arrow-right fs-5"></i></a>
        </div>
    </nav>

    <div class="pos-container">
        
        <!-- LEFT -->
        <div class="left-panel">
            <div class="search-area">
                <input type="text" id="mainInput" class="form-control main-input" placeholder="Escanear o buscar..." autocomplete="off" autofocus>
            </div>
            
            <div class="item-grid" id="productGrid">
                <?php foreach ($machines as $m): ?>
                <div class="product-card" onclick="initAdd('<?= $m['id'] ?>', '<?= addslashes($m['name']) ?>')">
                    <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 1px;"><?= $m['id'] ?></div>
                    <div class="p-name"><?= $m['name'] ?></div>
                    <div class="p-price">$<?= number_format($m['price'], 0) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="right-panel">
            <div class="cart-header">
                Ticket Actual
                <?php if(!empty($cart)): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Limpiar?')">
                    <input type="hidden" name="action" value="clear_cart">
                    <button class="btn btn-xs btn-link text-danger p-0 text-decoration-none">Borrar</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="cart-items">
                <?php if (empty($cart)): ?>
                    <div class="text-center text-muted mt-4 opacity-50">
                        <i class="bi bi-cart3 fs-1"></i>
                        <p class="small">Vacío</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart as $c): ?>
                    <div class="cart-row">
                        <div style="flex:1">
                            <div class="fw-bold text-dark small"><?= $c['name'] ?></div>
                            <small class="text-muted"><?= $c['qty'] ?> x</small>
                        </div>
                        <div class="fw-bold">$<?= number_format($c['total'], 0) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="totals-block">
                <div class="total-label">Total a Pagar</div>
                <div class="total-val">$<?= number_format($cart_total, 0) ?></div>
            </div>

            <!-- NUMPAD -->
            <div class="numpad-container">
                <button class="np-btn" onclick="sendKey('7')">7</button>
                <button class="np-btn" onclick="sendKey('8')">8</button>
                <button class="np-btn" onclick="sendKey('9')">9</button>
                
                <button class="np-btn" onclick="sendKey('4')">4</button>
                <button class="np-btn" onclick="sendKey('5')">5</button>
                <button class="np-btn" onclick="sendKey('6')">6</button>
                
                <button class="np-btn" onclick="sendKey('1')">1</button>
                <button class="np-btn" onclick="sendKey('2')">2</button>
                <button class="np-btn" onclick="sendKey('3')">3</button>

                <button class="np-btn np-clear" onclick="clearInput()"><i class="bi bi-backspace"></i></button>
                <button class="np-btn" onclick="sendKey('0')">0</button>
                <button class="np-btn" onclick="document.getElementById('mainInput').focus(); let e = new KeyboardEvent('keydown',{'key':'Enter'}); document.getElementById('mainInput').dispatchEvent(e);">Enter</button>
                
                <button class="np-btn np-pay" onclick="openCheckout('cash')" <?= empty($cart)?'disabled style="opacity:0.5"':'' ?>>COBRAR EFECTIVO</button>
                <?php if($mp_active): ?>
                <button class="np-btn np-pay" style="grid-column: span 3; background: #0ea5e9; box-shadow: 0 3px 0 #0284c7;" onclick="openCheckout('qr')" <?= empty($cart)?'disabled style="opacity:0.5"':'' ?>>MERCADO PAGO</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QTY MODAL -->
    <div class="modal fade" id="qtyModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-body p-4 text-center">
                    <h6 class="text-uppercase text-muted fw-bold small item-name">PRODUCTO</h6>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="machine_id" id="addId">
                        
                        <div class="qty-controls">
                            <button type="button" class="qty-btn qty-minus" onclick="adjustQty(-1)"><i class="bi bi-dash"></i></button>
                            <input type="number" name="quantity" id="addQty" class="form-control text-center fs-2 fw-bold border-0" value="1" min="1" autofocus style="width: 70px;">
                            <button type="button" class="qty-btn qty-plus" onclick="adjustQty(1)"><i class="bi bi-plus"></i></button>
                        </div>
                        
                        <button class="btn btn-primary w-100 rounded-pill py-2 fw-bold">AGREGAR (Enter)</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CASH MODAL -->
    <div class="modal fade" id="cashModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold">Pago en Efectivo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="display-4 fw-bold mb-4">$<?= number_format($cart_total, 0) ?></div>
                    <form method="POST" id="formCheckout">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="payment_method" value="cash">
                        <input type="number" name="amount_paid" id="cashInput" class="form-control form-control-lg text-center fw-bold mb-3" placeholder="Monto Entregado" autofocus>
                        <div class="fs-4 text-muted mb-4" id="changeText">Vuelto: $0.00</div>
                        <button class="btn btn-success w-100 btn-lg rounded-pill shadow">CONFIRMAR</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- QR MODAL -->
    <div class="modal fade" id="qrModal" tabindex="-1">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4">
                <div class="modal-body text-center p-5">
                    <h4 class="mb-3">Escanee QR</h4>
                    <div id="qr-code-container" class="mb-3 d-inline-block border p-2"></div>
                    <h2 class="fw-bold text-primary mb-4">$<?= number_format($cart_total, 0) ?></h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="payment_method" value="qr">
                        <button class="btn btn-primary w-100 rounded-pill">Confirmar Pago</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SHIFT INFO -->
    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Mi Cierre de Caja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <small class="text-muted text-uppercase">Turno del Día</small>
                        <h4 class="fw-bold"><?= date('d/m/Y', strtotime($shift_date)) ?></h4>
                        <small class="text-secondary">(Desde 09:00 AM)</small>
                    </div>
                    
                    <ul class="list-group mb-3">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold"><i class="bi bi-cash me-2"></i>Efectivo (Caja)</span>
                            <span class="fs-5">$<?= number_format($my_shift['total_cash'], 2) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-info fw-bold"><i class="bi bi-qr-code me-2"></i>Mercado Pago</span>
                            <span class="fs-5">$<?= number_format($my_shift['total_qr'], 2) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center bg-light">
                            <span class="fw-bold">TOTAL VENDIDO</span>
                            <span class="fs-4 fw-bold text-primary">$<?= number_format($my_shift['total_amount'], 2) ?></span>
                        </li>
                    </ul>
                    
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Estás viendo solo tus ventas de este turno.
                        <br>Transacciones: <strong><?= $my_shift['total_txns'] ?></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SUCCESS OVERLAY (RESTORED ORIGINAL) -->
    <?php if ($show_success): ?>
    <div class="success-overlay" id="successOverlay" onclick="closeOverlay()">
        <div class="container text-center">
            <div class="bg-white text-dark rounded-3 shadow-lg p-5 d-inline-block" style="min-width: 350px;">
                <h5 class="text-muted text-uppercase mb-4">Resumen de Venta</h5>
                
                <div class="mb-4 border-bottom pb-4">
                    <small class="text-muted d-block">Monto Total</small>
                    <div class="display-4 fw-bold text-primary">$<?= number_format($last_total_display, 2) ?></div>
                </div>

                <?php if ($last_change >= 0): ?>
                <div class="mb-4">
                    <small class="text-muted d-block">Su Vuelto</small>
                    <div class="display-2 fw-bold text-success">$<?= number_format($last_change, 2) ?></div>
                </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top">
                    <div class="text-success fs-2">
                        <i class="bi bi-check-circle-fill me-2"></i> ¡Venta Exitosa!
                    </div>
                    <div class="text-muted small mt-2">Presione cualquier tecla para continuar</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/vendor/qrcode/qrcode.min.js"></script>
    <script>
        const boxTotal = <?= $cart_total ?>;
        const mainInput = document.getElementById('mainInput');
        
        // Modals
        const qtyModalElement = document.getElementById('qtyModal');
        const qtyModal = new bootstrap.Modal(qtyModalElement);
        const qtyInput = document.getElementById('addQty');

        const cashModal = new bootstrap.Modal(document.getElementById('cashModal'));
        const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));

        // QR Generation for Mercado Pago
        function generateMPQR(amount, collectorId) {
            if (!collectorId) {
                // If no collector ID, show error
                return "SIN_CONFIGURACION_MP";
            }
            // Generate Mercado Pago payment URL
            // Format: https://api.mercadopago.com/sites/MLA/publications/{external_reference}/payments?payment_method=account_money&amount={amount}
            const externalRef = "SP" + Date.now();
            const country = "MLA"; // Argentina
            return `https://api.mercadopago.com/sites/${country}/publications/${externalRef}/payments?payment_method=account_money&amount=${amount}&collector_id=${collectorId}`;
        }

        // Initialize QR with current total
        const mpCollectorId = "<?= htmlspecialchars($mp_collector_id) ?>";
        const qrUrl = generateMPQR(boxTotal, mpCollectorId);
        
        // Clear previous QR and generate new one
        document.getElementById("qr-code-container").innerHTML = "";
        if (qrUrl !== "SIN_CONFIGURACION_MP") {
            new QRCode(document.getElementById("qr-code-container"), { 
                text: qrUrl, 
                width: 200, 
                height: 200,
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            document.getElementById("qr-code-container").innerHTML = '<div class="text-danger text-center p-3"><i class="bi bi-exclamation-triangle"></i><br>Configure el Collector ID en Mercado Pago</div>';
        }

        // Map Products
        const productsMap = {};
        <?php foreach ($machines as $m): ?>
        productsMap['<?= $m['id'] ?>'] = '<?= addslashes($m['name']) ?>';
        <?php endforeach; ?>
        
        // OVERLAY LOGIC
        function closeOverlay() {
            const el = document.getElementById('successOverlay');
            if (el) el.remove();
            mainInput.focus();
        }

        window.onload = () => { if(!document.getElementById('successOverlay')) mainInput.focus(); };

        // Helper: Numpad clicks send keys to Main Input
        function sendKey(k) {
            mainInput.value += k;
            mainInput.focus();
        }
        function clearInput() {
            mainInput.value = '';
            mainInput.focus();
        }

        // Qty Control
        function adjustQty(delta) {
            let val = parseInt(qtyInput.value) || 1;
            val += delta;
            if(val < 1) val = 1;
            qtyInput.value = val;
            qtyInput.focus(); 
            qtyInput.select(); 
        }

        // Main Input Handlers
        mainInput.addEventListener('keydown', (e) => {
            // IF OVERLAY IS OPEN, CLOSE IT
            if (document.getElementById('successOverlay')) {
                closeOverlay();
                e.preventDefault();
                return;
            }

            if(e.key === 'Enter') {
                const val = mainInput.value.trim().toUpperCase();
                if(val) {
                    if(productsMap[val]) initAdd(val, productsMap[val]);
                    else alert('Producto no encontrado');
                    mainInput.value = '';
                } else {
                    if(boxTotal > 0) openCheckout('cash');
                }
            }
        });
        
        // Also listen on document for closing overlay
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('successOverlay')) {
                closeOverlay();
                e.preventDefault();
            }
        });

        function initAdd(id, name) {
            document.getElementById('addId').value = id;
            document.querySelector('.item-name').innerText = name;
            qtyInput.value = 1;
            qtyModal.show();
        }

        // AUTO-SELECT on Modal Show
        qtyModalElement.addEventListener('shown.bs.modal', function () {
            qtyInput.focus();
            qtyInput.select();
        });

        function openCheckout(type) {
            if(type === 'cash') {
                cashModal.show();
                setTimeout(() => document.getElementById('cashInput').focus(), 500);
            }
            if(type === 'qr') {
                // Refresh QR with current total
                const newQrUrl = generateMPQR(boxTotal, mpCollectorId);
                document.getElementById("qr-code-container").innerHTML = "";
                if (newQrUrl !== "SIN_CONFIGURACION_MP") {
                    new QRCode(document.getElementById("qr-code-container"), { 
                        text: newQrUrl, 
                        width: 200, 
                        height: 200,
                        correctLevel: QRCode.CorrectLevel.M
                    });
                } else {
                    document.getElementById("qr-code-container").innerHTML = '<div class="text-danger text-center p-3"><i class="bi bi-exclamation-triangle"></i><br>Configure el Collector ID</div>';
                }
                qrModal.show();
            }
        }

        // Cash Change Calc
        document.getElementById('cashInput').addEventListener('input', function() {
            const val = parseFloat(this.value) || 0;
            const diff = val - boxTotal;
            const el = document.getElementById('changeText');
            if(diff >= 0) { el.innerText = 'Vuelto: $' + diff.toFixed(0); el.className = 'fs-3 fw-bold text-success mb-4'; }
            else { el.innerText = 'Falta: $' + Math.abs(diff).toFixed(0); el.className = 'fs-4 text-muted mb-4'; }
        });
        
        document.getElementById('formCheckout').addEventListener('submit', function() {
             if(document.getElementById('cashInput').value === '') document.getElementById('cashInput').value = boxTotal;
        });

        // --- BACKGROUND AUTO-SYNC (Solo en Local) ---
        <?php 
        $driver = \Database::getInstance()->getDriver();
        if ($driver === 'sqlite'): 
        ?>
        function runAutoSync() {
            console.log("Iniciando autosync POS...");
            fetch('../scripts/sync_upload.php').catch(e => console.error("Error auto-upload:", e));
            if (new Date().getMinutes() % 15 === 0) {
                fetch('../scripts/sync_pull.php').catch(e => console.error("Error auto-pull:", e));
            }
        }
        setInterval(runAutoSync, 5 * 60 * 1000);
        setTimeout(runAutoSync, 60 * 1000); // Dar un minuto al iniciar
        <?php endif; ?>
    </script>
</body>
</html>
