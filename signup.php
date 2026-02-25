<?php
// signup.php
require_once __DIR__ . '/src/Database.php';
$db = Database::getInstance()->getConnection();

$plan_id = $_GET['plan_id'] ?? null;
if (!$plan_id) { header('Location: landing.php'); exit; }

$stmt = $db->prepare("SELECT * FROM plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();
if (!$plan) die("Plan inválido");

// Defaults for older plans without these columns
$allow_mp = isset($plan['allow_mp_integration']) ? $plan['allow_mp_integration'] : 1;
$mp_fee = isset($plan['mp_fee']) ? (float)$plan['mp_fee'] : 0;
$pos_limit = isset($plan['pos_limit']) ? (int)$plan['pos_limit'] : 1;
$pos_extra_fee = isset($plan['pos_extra_fee']) ? (float)$plan['pos_extra_fee'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscripción - SpacePark</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="card shadow border-0 rounded-4">
                <div class="card-header bg-primary text-white p-4 rounded-top-4">
                    <h4 class="mb-0 fw-bold">🚀 Alta de Servicio</h4>
                    <p class="mb-0 opacity-75">Configuración final del plan <strong><?= htmlspecialchars($plan['name']) ?></strong></p>
                </div>
                <div class="card-body p-4">
                    
                    <form action="api/process_signup.php" method="POST" id="signupForm">
                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                        
                        <!-- 1. RESUMEN Y EXTRAS -->
                        <h5 class="text-primary mb-3"><i class="bi bi-cart-check"></i> Personalice su Suscripción</h5>
                        <div class="alert alert-light border shadow-sm">
                            <!-- BASE -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bi bi-box-seam me-2"></i> Plan Base (Incluye <strong><?= $pos_limit ?></strong> POS<?= $plan['period'] == 'monthly' ? ' + Mercado Pago' : '' ?>)</span>
                                <strong id="displayBasePrice">$<?= number_format($plan['price'], 2) ?></strong>
                            </div>

                            <!-- MP INTEGRATION -->
                            <?php if($allow_mp): ?>
                            <div class="form-check form-switch py-2 border-top">
                                <input class="form-check-input" type="checkbox" id="addMp" name="add_mp" value="1" <?= $mp_fee == 0 ? 'checked disabled' : '' ?>>
                                <label class="form-check-label d-block" for="addMp">
                                    <strong>Integración Mercado Pago</strong>
                                    <?php if($mp_fee > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1">+$<?= number_format($mp_fee, 0) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success ms-1">✓ Incluido</span>
                                    <?php endif; ?>
                                </label>
                                <div class="small text-muted fst-italic">Cobre con QR y Link de Pago integrados.</div>
                            </div>
                            <?php endif; ?>

                            <!-- MODO INTEGRATION -->
                            <?php 
                                $allow_modo = isset($plan['allow_modo_integration']) ? $plan['allow_modo_integration'] : 0;
                                $modo_fee = isset($plan['modo_fee']) ? (float)$plan['modo_fee'] : 0;
                            ?>
                            <?php if($allow_modo): ?>
                            <div class="form-check form-switch py-2 border-top">
                                <input class="form-check-input" type="checkbox" id="addModo" name="add_modo" value="1">
                                <label class="form-check-label d-block" for="addModo">
                                    <strong>Integración MODO</strong>
                                    <?php if($modo_fee > 0): ?>
                                        <span class="badge bg-warning text-dark ms-1">+$<?= number_format($modo_fee, 0) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success ms-1">Incluido</span>
                                    <?php endif; ?>
                                </label>
                                <div class="small text-muted fst-italic">Habilite cobros con Billetera MODO.</div>
                            </div>
                            <?php endif; ?>

                            <!-- EXTRA POS -->
                            <?php if($pos_extra_fee > 0): ?>
                            <div class="py-2 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label mb-0"><strong>Puntos de Venta (POS) Adicionales</strong></label>
                                    <div class="input-group input-group-sm" style="width: 130px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="changePos(-1)">-</button>
                                        <input type="text" class="form-control text-center" id="extraPosInput" name="extra_pos" value="0" readonly>
                                        <button class="btn btn-outline-secondary" type="button" onclick="changePos(1)">+</button>
                                    </div>
                                </div>
                                <div class="small text-muted text-end mt-1">
                                    Costo por extra: $<?= number_format($pos_extra_fee, 0) ?>/u
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- TOTAL -->
                            <div class="mt-3 pt-3 border-top border-2 d-flex justify-content-between align-items-center fs-4 bg-white p-2 rounded">
                                <strong>TOTAL <?= strtoupper($plan['period'] == 'monthly' ? 'MENSUAL' : ($plan['period'] == 'quarterly' ? 'TRIMESTRAL' : 'ANUAL')) ?>:</strong>
                                <strong class="text-primary" id="displayTotal">$...</strong>
                            </div>
                        </div>

                        <!-- 2. DATOS -->
                        <h5 class="text-primary mt-4 mb-3 border-bottom pb-2"><i class="bi bi-shop"></i> Datos del Negocio</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Negocio</label>
                                <input type="text" name="business_name" class="form-control" required placeholder="Ej: Mi Kiosco">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dirección Web (Subdominio)</label>
                                <div class="input-group">
                                    <input type="text" name="subdomain" id="subdomainInput" class="form-control" required placeholder="negocio" pattern="[a-z0-9-]+" title="Minúsculas y números">
                                    <span class="input-group-text bg-light fw-bold flex-shrink-1 text-truncate">.tevsko.com.ar</span>
                                </div>
                                <div id="subdomainFeedback" class="form-text">Click fuera para verificar.</div>
                            </div>
                        </div>

                        <h5 class="text-primary mt-3 mb-3 border-bottom pb-2"><i class="bi bi-person-circle"></i> Usuario Administrador</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg fw-bold rounded-pill shadow-sm">
                                CONTINUAR AL PAGO <i class="bi bi-arrow-right"></i>
                            </button>
                            <a href="landing.php" class="btn btn-link text-decoration-none text-muted text-center">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Config Prices form PHP
    const basePrice = <?= (float)$plan['price'] ?>;
    const mpFee = <?= (float)$mp_fee ?>;
    const modoFee = <?= (float)($modo_fee ?? 0) ?>;
    const posFee = <?= (float)$pos_extra_fee ?>;
    
    // Elements
    const mpCheckbox = document.getElementById('addMp');
    const modoCheckbox = document.getElementById('addModo');
    const posInput = document.getElementById('extraPosInput');
    const totalDisplay = document.getElementById('displayTotal');

    function updateTotal() {
        let total = basePrice;
        
        // Add MP Fee
        if (mpCheckbox && mpCheckbox.checked) {
            total += mpFee;
        }
        // Add MODO Fee
        if (modoCheckbox && modoCheckbox.checked) {
            total += modoFee;
        }
        
        // Add POS Fee
        if (posInput) {
            let count = parseInt(posInput.value) || 0;
            total += (count * posFee);
        }
        
        totalDisplay.innerText = '$' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // Events
    if (mpCheckbox) mpCheckbox.addEventListener('change', updateTotal);
    if (modoCheckbox) modoCheckbox.addEventListener('change', updateTotal);
    
    function changePos(delta) {
        if(!posInput) return;
        let val = parseInt(posInput.value) || 0;
        val += delta;
        if(val < 0) val = 0;
        posInput.value = val;
        updateTotal();
    }
    // Expose needed function globally for button onclick
    window.changePos = changePos;

    // Init
    updateTotal();

    // -- Subdomain Check --
    const subInput = document.getElementById('subdomainInput');
    const feedback = document.getElementById('subdomainFeedback');

    subInput.addEventListener('blur', function() {
        const val = this.value.trim().toLowerCase();
        if(!val) return;
        fetch(`api/check_subdomain.php?subdomain=${val}`)
            .then(r => r.json())
            .then(data => {
                if(data.available) {
                    subInput.classList.remove('is-invalid'); subInput.classList.add('is-valid');
                    feedback.className = 'form-text text-success'; feedback.innerText = '¡Disponible!';
                } else {
                    subInput.classList.remove('is-valid'); subInput.classList.add('is-invalid');
                    feedback.className = 'form-text text-danger'; feedback.innerText = 'Ocupado o Reservado.';
                }
            });
    });
    subInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    });
</script>
</body>
</html>
