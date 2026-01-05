<?php
// pos/index.php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Uuid.php';

$auth = new Auth();
$auth->requireRole('employee');
$currentUser = $auth->getCurrentUser();
$branch_id = $currentUser['branch_id'];

$db = Database::getInstance()->getConnection();

// Cargar Config Sucursal (Nube, MP, Titulo)
$stmt = $db->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$branch_id]);
$branch = $stmt->fetch();

if (!$branch) {
    die("Error: Sucursal no identificada.");
}

// ---------------------------------------------------------
// VALIDACIÓN DE LICENCIA MÓDULO POS
// ---------------------------------------------------------
$license_pos_expiry = $branch['license_pos_expiry'];
$is_pos_licensed = false;
$pos_msg = "";

if ($license_pos_expiry) {
    $today = date('Y-m-d');
    if ($license_pos_expiry >= $today) {
        $is_pos_licensed = true;
    } else {
        $pos_msg = "El Módulo POS venció el " . date('d/m/Y', strtotime($license_pos_expiry));
    }
} else {
    // Si es NULL, asumimos que no tiene el módulo contratado
    $pos_msg = "El Módulo POS no está habilitado para esta sucursal.";
}

if (!$is_pos_licensed) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Módulo POS Bloqueado</title>
        <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow border-warning" style="max-width: 500px;">
            <div class="card-header bg-warning text-dark fw-bold">
                ⚠️ MÓDULO POS INACTIVO
            </div>
            <div class="card-body text-center p-5">
                <i class="bi bi-cart-x text-warning" style="font-size: 4rem;"></i>
                <h3 class="mt-3">Ventas Deshabilitadas</h3>
                <p class="lead mt-3"><?= htmlspecialchars($pos_msg) ?></p>
                <hr>
                <p class="small text-muted">Aún puede acceder con su usuario, pero no realizar ventas.</p>
                <a href="../logout.php" class="btn btn-outline-secondary mt-3">Cerrar Sesión</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------------------------------------------------------
// VALIDACIÓN LICENCIA MERCADO PAGO
// ---------------------------------------------------------
$license_mp_expiry = $branch['license_mp_expiry'];
$mp_licensed = ($license_mp_expiry && $license_mp_expiry >= date('Y-m-d'));

$pos_title = $branch['pos_title'] ?? 'SpacePark POS';
// MP Active IF status is 1 AND license is valid
$mp_active = ($branch['mp_status'] == 1 && $mp_licensed);
$mp_token = $branch['mp_token'] ?? '';

$message = '';
$error = '';
// Variables para mensaje de éxito post-venta
$last_total = $_SESSION['last_total'] ?? 0;
$last_paid = $_SESSION['last_paid'] ?? 0;
$last_change = $_SESSION['last_change'] ?? -1;
$last_method = $_SESSION['last_method'] ?? '';

// Limpiar flashes después de obtenerlos
if (isset($_SESSION['last_total'])) {
    unset($_SESSION['last_total']);
    unset($_SESSION['last_paid']);
    unset($_SESSION['last_change']);
    unset($_SESSION['last_method']);
    $show_success = true;
} else {
    $show_success = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_item') {
            $machine_id = strtoupper($_POST['machine_id']);
            $qty = (int)$_POST['quantity'];
            
            $stmt = $db->prepare("SELECT * FROM machines WHERE id = ? AND active = 1");
            $stmt->execute([$machine_id]);
            $machine = $stmt->fetch();
            
            if ($machine) {
                if ($machine['branch_id'] && $machine['branch_id'] !== $branch_id) {
                    $error = "Producto de otro local.";
                } else {
                    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['id'] === $machine['id']) {
                            $item['qty'] += $qty;
                            $item['total'] = $item['price'] * $item['qty'];
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $_SESSION['cart'][] = [
                            'id' => $machine['id'],
                            'name' => $machine['name'],
                            'price' => $machine['price'],
                            'qty' => $qty,
                            'total' => $machine['price'] * $qty
                        ];
                    }
                }
            } else {
                $error = "Producto no encontrado.";
            }
        }
        elseif ($_POST['action'] === 'clear_cart') {
            $_SESSION['cart'] = [];
        }
        elseif ($_POST['action'] === 'checkout') {
            $method = $_POST['payment_method']; // cash / qr
            $amount_paid = (float)($_POST['amount_paid'] ?? 0);
            $cart = $_SESSION['cart'] ?? [];
            $cart_total = 0;
            foreach ($cart as $c) $cart_total += $c['total'];
            
            // Lógica "Pago Justo" (Enter sin monto)
            if ($method === 'cash' && $amount_paid == 0) {
                $amount_paid = $cart_total;
            }
            
            if (empty($cart)) {
                $error = "Carrito vacío.";
            } elseif ($method === 'cash' && $amount_paid < $cart_total) {
                $error = "Monto insuficiente.";
            } else {
                try {
                    $db->beginTransaction();
                    $total_txn = 0;
                    foreach ($cart as $item) {
                        $sale_id = Uuid::generate();
                        $total_line = $item['total'];
                        $stmt = $db->prepare("INSERT INTO sales (id, user_id, branch_id, machine_id, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$sale_id, $currentUser['id'], $branch_id, $item['id'], $total_line, $method]);
                        $total_txn += $total_line;
                    }
                    $db->commit();
                    
                    // Guardar datos para flash success screen
                    $_SESSION['last_total'] = $total_txn;
                    $_SESSION['last_paid'] = ($method === 'qr') ? $total_txn : $amount_paid;
                    $_SESSION['last_change'] = ($method === 'qr') ? 0 : ($amount_paid - $total_txn);
                    $_SESSION['last_method'] = $method;

                    $_SESSION['cart'] = []; 
                    
                    // Redirect to self to clear POST and show overlay
                    header("Location: index.php");
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error al procesar: " . $e->getMessage();
                }
            }
        }
    }
}

// Data Loading
$sql = "SELECT * FROM machines WHERE active = 1";
if ($branch_id) $sql .= " AND (branch_id IS NULL OR branch_id = '$branch_id')";
else $sql .= " AND branch_id IS NULL"; 
$machines = $db->query($sql)->fetchAll();

$cart = $_SESSION['cart'] ?? [];
$cart_total = 0;
foreach ($cart as $c) $cart_total += $c['total'];

$stmt = $db->prepare("SELECT s.*, m.name as m_name FROM sales s JOIN machines m ON s.machine_id = m.id WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 5");
$stmt->execute([$currentUser['id']]);
$recent_sales = $stmt->fetchAll();

// --- LÓGICA DE CIERRE DE CAJA (Usuario Actual) ---
// Turno: 09:00 AM a 09:00 AM del día siguiente
$cur_h = (int)date('H');
$shift_date = ($cur_h < 9) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
$shift_start = $shift_date . ' 09:00:00';
$shift_end   = date('Y-m-d H:i:s', strtotime($shift_start . ' +1 day'));

// Calcular totales del turno del usuario
$stmt = $db->prepare("SELECT 
    COUNT(*) as total_txns,
    COALESCE(SUM(amount),0) as total_amount,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END),0) as total_cash,
    COALESCE(SUM(CASE WHEN payment_method = 'qr' THEN amount ELSE 0 END),0) as total_qr
    FROM sales 
    WHERE user_id = ? AND created_at >= ? AND created_at < ?");
$stmt->execute([$currentUser['id'], $shift_start, $shift_end]);
$my_shift = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pos_title) ?></title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .navbar { flex-shrink: 0; }
        .main-container { flex: 1; display: flex; overflow: hidden; }
        .left-panel { flex: 2; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; }
        .grid-products { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
        .card-product { background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; text-align: center; cursor: pointer; transition: 0.1s; }
        .card-product:hover { border-color: #0d6efd; background: #f0f8ff; }
        .card-product:active { transform: scale(0.98); }
        .card-product .price { font-weight: bold; color: #198754; font-size: 1.1em; }
        .right-panel { flex: 1; background: white; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .cart-list { flex: 1; overflow-y: auto; padding: 0; }
        .cart-item { padding: 10px 15px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .cart-item:nth-child(even) { background-color: #fafafa; }
        .billing-section { background: #f1f3f5; padding: 20px; border-top: 2px solid #ced4da; }
        .recent-box { margin-top: auto; padding-top: 20px; }
        .recent-row { font-size: 0.85rem; padding: 5px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .success-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #198754;
            z-index: 9999; display: flex; justify-content: center; align-items: center; flex-direction: column; color: white;
            animation: fadeIn 0.2s; cursor: pointer;
        }
        .pay-input { font-size: 2.5rem; text-align: center; font-weight: bold; color: #198754; }
        .modal-qty-input { font-size: 3rem; text-align: center; font-weight: bold; border: 3px solid #0d6efd; border-radius: 10px; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand mb-0 h1"><?= htmlspecialchars($pos_title) ?></span>
    <div class="text-white small">
        <i class="bi bi-person-fill"></i> <?= htmlspecialchars($currentUser['username']) ?> 
        <button class="btn btn-sm btn-outline-light ms-3" data-bs-toggle="modal" data-bs-target="#shiftModal">
            <i class="bi bi-cash-coin me-1"></i> Mi Cierre
        </button>
        <a href="../logout.php" class="ms-2 text-warning text-decoration-none">Salir</a>
    </div>
</nav>

<!-- MODAL DE CIERRE DE TURNO -->
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

<div class="main-container">
    <div class="left-panel">
        <div class="mb-3">
            <input type="text" id="mainInput" class="form-control form-control-lg" placeholder="Código / ID (Foco Automático)" autocomplete="off">
        </div>
        <div class="grid-products">
            <?php foreach ($machines as $m): ?>
            <div class="card-product" onclick="initAdd('<?= $m['id'] ?>', '<?= $m['name'] ?>')">
                <div class="small text-muted"><?= $m['id'] ?></div>
                <div class="fw-bold text-truncate"><?= $m['name'] ?></div>
                <div class="price">$<?= number_format($m['price'], 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="recent-box">
            <h6 class="text-muted border-bottom pb-2">Últimos Movimientos</h6>
            <?php foreach ($recent_sales as $rs): ?>
            <div class="recent-row">
                <span><?= htmlspecialchars($rs['m_name']) ?></span>
                <span class="fw-bold">$<?= number_format($rs['amount'], 0) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="right-panel">
        <div class="p-2 bg-light border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold">Ticket Actual</span>
            <form method="POST" id="formClear">
                <input type="hidden" name="action" value="clear_cart">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="doClear()">Borrar (Esc)</button>
            </form>
        </div>
        <div class="cart-list" id="cartContainer">
            <?php if (empty($cart)): ?>
                <div class="text-center text-muted mt-5 px-3">
                    <p>Ingrese productos</p>
                </div>
            <?php else: ?>
                <?php foreach ($cart as $it): ?>
                <div class="cart-item">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($it['name']) ?></div>
                        <small class="text-muted"><?= $it['qty'] ?> x $<?= number_format($it['price'], 2) ?></small>
                    </div>
                    <div class="fs-5">$<?= number_format($it['total'], 2) ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="billing-section">
            <div class="d-flex justify-content-between mb-3">
                <span class="h4 mb-0">TOTAL</span>
                <span class="h2 mb-0 fw-bold text-primary">$<?= number_format($cart_total, 2) ?></span>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-success btn-lg" onclick="openCheckout('cash')" <?= empty($cart)?'disabled':'' ?>>
                    COBRAR EFECTIVO (Enter)
                </button>
                <?php if ($mp_active): ?>
                <button class="btn btn-info btn-lg text-white" onclick="openCheckout('qr')" <?= empty($cart)?'disabled':'' ?>>
                    COBRAR QR (F2)
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qtyModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body p-4 text-center">
                <h5 class="mb-3 item-name">Producto</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="machine_id" id="addId">
                    <div class="mb-1 text-start fw-bold small">Cantidad:</div>
                    <input type="number" name="quantity" id="addQty" class="form-control modal-qty-input" value="1" min="1" required>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cashModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Cobrar en Efectivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-2 text-muted">Total a Pagar</div>
                <h1 class="display-4 fw-bold mb-4">$<?= number_format($cart_total, 2) ?></h1>
                
                <form method="POST" id="formCheckout">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="payment_method" value="cash">
                    
                    <div class="mb-3 text-start">
                        <label class="form-label fw-bold">Su Pago (Enter = Justo)</label>
                        <input type="number" name="amount_paid" id="cashInput" class="form-control pay-input" placeholder="$" step="0.01">
                    </div>
                    
                    <div class="change-display text-muted fs-4" id="changeText">Vuelto: $0.00</div>
                    
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-4 p-3">CONFIRMAR</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <h3 class="mb-4">Escanee para Pagar</h3>
                <!-- QR Placeholder Local (Generado por JS) -->
                <div id="qr-code-container" class="mb-4 border p-2 d-flex justify-content-center"></div>
                <h2 class="text-primary fw-bold">$<?= number_format($cart_total, 2) ?></h2>
                <form method="POST">
                    <input type="hidden" name="action" value="checkout">
                    <input type="hidden" name="payment_method" value="qr">
                    <button class="btn btn-primary w-100 mt-3">Confirmar Pago</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SUCCESS OVERLAY MEJORADO -->
<?php if ($show_success): ?>
<div class="success-overlay" id="successOverlay" onclick="closeOverlay()">
    <div class="container text-center">
        <div class="bg-white text-dark rounded-3 shadow-lg p-5 d-inline-block" style="min-width: 350px;">
            <h5 class="text-muted text-uppercase mb-4">Resumen de Venta</h5>
            
            <div class="mb-4 border-bottom pb-4">
                <small class="text-muted d-block">Monto Total</small>
                <div class="display-4 fw-bold text-primary">$<?= number_format($last_total, 2) ?></div>
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
    // Generar QR Localmente
    new QRCode(document.getElementById("qr-code-container"), {
        text: "SpaceParkPlay",
        width: 200,
        height: 200
    });
</script>
<script>
    const boxTotal = <?= $cart_total ?>;
    const qtyModal = new bootstrap.Modal(document.getElementById('qtyModal'));
    const cashModal = new bootstrap.Modal(document.getElementById('cashModal'));
    const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    const mainInput = document.getElementById('mainInput');
    const qtyInput = document.getElementById('addQty');
    const cashInput = document.getElementById('cashInput');

    const productsMap = {};
    <?php foreach ($machines as $m): ?>
    productsMap['<?= $m['id'] ?>'] = '<?= addslashes($m['name']) ?>';
    <?php endforeach; ?>

    window.onload = function() {
        if (!document.getElementById('successOverlay')) mainInput.focus();
    };

    function closeOverlay() {
        const el = document.getElementById('successOverlay');
        if (el) el.remove();
        mainInput.focus();
    }
    
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('successOverlay')) {
            closeOverlay();
            e.preventDefault();
            return;
        }
        
        // FKeys Global
        if (e.key === 'F1') { e.preventDefault(); if (boxTotal > 0) openCheckout('cash'); }
        <?php if ($mp_active): ?>
        if (e.key === 'F2') { e.preventDefault(); if (boxTotal > 0) openCheckout('qr'); }
        <?php endif; ?>
        if (e.key === 'Escape') {
            if (document.querySelector('.modal.show')) {
                bootstrap.Modal.getInstance(document.querySelector('.modal.show')).hide();
                mainInput.focus();
            } else {
                doClear();
            }
            e.preventDefault();
        }
    });

    // Validar Enter en Pagos
    document.getElementById('formCheckout').addEventListener('submit', function(e) {
        // "Si su pago es justo no debería hacer falta ingresar el monto"
        if (cashInput.value === '') {
            cashInput.value = boxTotal; // Asumir pago exacto
        }
    });

    mainInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const val = this.value.trim().toUpperCase();
            if (val !== '') {
                if (productsMap.hasOwnProperty(val)) {
                    initAdd(val, productsMap[val]);
                    this.value = '';
                } else {
                    // Feedback visual sutil o alerta
                    alert('Código incorrecto: ' + val);
                    this.select();
                }
                e.preventDefault();
            } else {
                if (boxTotal > 0) openCheckout('cash');
                e.preventDefault();
            }
        }
    });

    const qtyEl = document.getElementById('qtyModal');
    qtyEl.addEventListener('shown.bs.modal', () => {
        qtyInput.focus();
        qtyInput.select();
    });

    const cashEl = document.getElementById('cashModal');
    cashEl.addEventListener('shown.bs.modal', () => {
        cashInput.value = '';
        document.getElementById('changeText').innerText = 'Vuelto: $0.00';
        document.getElementById('changeText').className = 'change-display text-muted fs-4';
        cashInput.focus();
    });

    cashInput.addEventListener('input', function() {
        const paid = parseFloat(this.value) || 0;
        const change = paid - boxTotal;
        const disp = document.getElementById('changeText');
        if (change >= 0) {
            disp.innerText = 'Vuelto: $' + change.toFixed(2);
            disp.className = 'change-display text-success fs-2 fw-bold';
        } else {
            disp.innerText = 'Falta: $' + Math.abs(change).toFixed(2);
            disp.className = 'change-display text-danger fs-4';
        }
    });

    function initAdd(id, name) {
        document.getElementById('addId').value = id;
        document.querySelector('.item-name').innerText = name || id;
        qtyModal.show();
    }

    function openCheckout(method) {
        if (method === 'cash') cashModal.show();
        if (method === 'qr') qrModal.show();
    }

    function doClear() {
        if (confirm('¿Limpiar carrito?')) {
            document.getElementById('formClear').submit();
        } else {
            mainInput.focus();
        }
    }
</script>
</body>
</html>
