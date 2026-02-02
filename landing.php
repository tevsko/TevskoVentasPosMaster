<?php
require_once __DIR__ . '/src/Database.php';
$db = Database::getInstance()->getConnection();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pendingSub = $_SESSION['pending_subscription_id'] ?? null;

$plans = $db->query("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC")->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Planes - SpacePark</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-5">
  <div class="text-center mb-4">
    <h1>Planes y Precios</h1>
    <p class="text-muted">Elige un plan y paga con Mercado Pago. Probaremos en modo Sandbox.</p>
  </div>

  <?php if (isset($_GET['status']) && $_GET['status'] === 'success' && $pendingSub): ?>
    <div class="alert alert-info">
      <strong>Gracias por tu pago.</strong> Estamos procesando la provisión. En unos segundos aparecerá el enlace de provisioning o lo recibirás por correo.
    </div>
    <div id="provisionBox" class="card mb-3" style="display:block">
      <div class="card-body">
        <p id="provStatus">Verificando provisión...</p>
        <div id="provLink" style="display:none">
          <p>Provisioning listo: <a id="provHref" href="#" target="_blank">Abrir página de provisioning</a></p>
        </div>
      </div>
    </div>
    <script>
      (function(){
        var pending = <?= json_encode($pendingSub) ?>;
        function check() {
          fetch('/api/check_provision.php?subscription_id=' + encodeURIComponent(pending)).then(function(res){ return res.json(); }).then(function(j){
            if (j.ok && j.provisioned) {
              document.getElementById('provStatus').textContent = 'Provisioning completado.';
              document.getElementById('provHref').href = j.provisioning_url;
              document.getElementById('provLink').style.display = 'block';
              // Clear session pending flag
              fetch('/api/clear_pending_session.php?subscription_id=' + encodeURIComponent(pending));
            } else {
              // still pending
              setTimeout(check, 3000);
            }
          }).catch(function(){ setTimeout(check, 3000); });
        }
        check();
      })();
    </script>
  <?php elseif (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-info"><strong>Gracias por tu pago.</strong> Estamos procesando la provisión; recibirás un email con el enlace cuando esté listo.</div>
  <?php endif; ?>

  <div class="row">
    <?php foreach ($plans as $plan): ?>
      <div class="col-md-4 mb-3">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title"><?= htmlspecialchars($plan['name']) ?></h5>
            <p class="card-text">Precio: <strong>$<?= number_format($plan['price'],2) ?></strong> (<?= $plan['period'] ?>)</p>
            <p class="text-muted small"><?= htmlspecialchars(implode(', ', json_decode($plan['features'] ?? '[]') ?: [])) ?></p>
            <form method="POST" action="api/subscribe.php" class="mt-auto">
              <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
              <button class="btn btn-primary w-100">Contratar</button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center mt-4">
    <small class="text-muted">¿Necesitas una demo o factura masiva? Contáctanos.</small>
    <p class="text-muted small mt-2">Nota: tras la compra recibirás un email con un enlace de provisioning que contiene tu <strong>Sync Token</strong> y un QR para configurarlo rápidamente en la instalación local.</p>
  </div>
</div>
</body>
</html>