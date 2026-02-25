<?php
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/LandingHelpers.php';

$db = Database::getInstance()->getConnection();
LandingHelpers::init($db);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Registrar visita
LandingHelpers::registerVisit();

// Cargar datos dinámicos
$carouselSlides = LandingHelpers::getActiveCarousel();
$features = LandingHelpers::getActiveFeatures();
$testimonials = LandingHelpers::getActiveTestimonials();
$plans = $db->query("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC")->fetchAll();

// Settings
$metaTitle = LandingHelpers::getSetting('meta_title', 'SpacePark - Tu Solución de Punto de Venta');
$metaDescription = LandingHelpers::getSetting('meta_description', 'Sistema de punto de venta moderno con integración de Mercado Pago y gestión en la nube');
$metaKeywords = LandingHelpers::getSetting('meta_keywords', 'punto de venta, pos, mercado pago, sistema de ventas');

// WhatsApp
$whatsappEnabled = LandingHelpers::getSetting('whatsapp_enabled', '1') == '1';
$whatsappNumber = LandingHelpers::getSetting('whatsapp_number', '541135508224');
$whatsappMessage = LandingHelpers::getSetting('whatsapp_message', 'Hola! Quiero información sobre SpacePark POS');

// Popup
$popupEnabled = LandingHelpers::getSetting('popup_enabled', '0') == '1';
$popupTitle = LandingHelpers::getSetting('popup_title', '¡Oferta Especial!');
$popupContent = LandingHelpers::getSetting('popup_content', '');
$popupImageUrl = LandingHelpers::getSetting('popup_image_url', '');
$popupFrequency = LandingHelpers::getSetting('popup_frequency', 'once_per_session');

// Testimonials
$testimonialsEnabled = LandingHelpers::getSetting('testimonials_enabled', '1') == '1';

// Social Media
$facebookUrl = LandingHelpers::getSetting('facebook_url', '');
$instagramUrl = LandingHelpers::getSetting('instagram_url', '');
$twitterUrl = LandingHelpers::getSetting('twitter_url', '');
$linkedinUrl = LandingHelpers::getSetting('linkedin_url', '');

// ARCA Data Fiscal
$arcaEnabled = LandingHelpers::getSetting('arca_enabled', '0') == '1';
$arcaQrUrl = LandingHelpers::getSetting('arca_qr_url', 'http://qr.arca.gob.ar/?qr=bzxycYWFjNx2rzg0Skbz_g,,');
$arcaImageUrl = LandingHelpers::getSetting('arca_image_url', 'https://www.arca.gob.ar/images/f960/DATAWEB.jpg');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($metaTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
  
  <!-- Fonts & Icons -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;800&display=swap" rel="stylesheet">

  <style>
    :root {
        --primary-color: #4a90e2;
        --secondary-color: #2c3e50;
        --bg-color: #f4f8fb;
        --accent-color: #0ea5e9;
    }
    
    body {
        font-family: 'Open Sans', sans-serif;
        background-color: var(--bg-color);
        color: var(--secondary-color);
        overflow-x: hidden;
    }

    /* --- Navbar --- */
    .navbar {
        background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%);
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        padding: 1rem 0;
    }
    .navbar-brand {
        font-weight: 800;
        font-size: 1.5rem;
        color: white !important;
        letter-spacing: 1px;
    }
    .btn-login-nav {
        background: rgba(255,255,255,0.1);
        color: white;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 50px;
        padding: 0.5rem 1.5rem;
        transition: all 0.3s;
        font-weight: 600;
        text-decoration: none;
    }
    .btn-login-nav:hover {
        background: white;
        color: var(--primary-color);
    }

    /* --- Carousel --- */
    .carousel-item {
        height: 500px;
        background-color: #333;
        position: relative;
    }
    .carousel-item img {
        object-fit: cover;
        height: 100%;
        width: 100%;
        opacity: 0.6;
    }
    .carousel-caption {
        bottom: 20%;
        text-shadow: 0 2px 4px rgba(0,0,0,0.6);
    }
    .carousel-caption h1 {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
    }
    .carousel-caption p {
        font-size: 1.5rem;
        font-weight: 300;
    }

    /* --- Features Section --- */
    .features-section {
        background: white;
    }
    .feature-card {
        padding: 2rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        transition: all 0.3s;
        height: 100%;
        border: 1px solid #f0f0f0;
    }
    .feature-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        border-color: var(--primary-color);
    }

    /* --- Testimonials --- */
    .testimonials-section {
        background: #f8f9fa;
    }
    .testimonial-card {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        height: 100%;
    }

    /* --- Pricing Section --- */
    .pricing-header {
        text-align: center;
        margin: 4rem 0 3rem;
    }
    .pricing-header h2 {
        font-weight: 800;
        color: var(--secondary-color);
        font-size: 2.5rem;
    }
    .pricing-card {
        border: none;
        border-radius: 15px;
        background: white;
        transition: all 0.3s ease;
        overflow: hidden;
        height: 100%;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        position: relative;
        display: flex;
        flex-direction: column;
    }
    .pricing-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    .pricing-card-header {
        background: var(--primary-color);
        color: white;
        padding: 2.5rem 1rem;
        text-align: center;
        border-bottom: 5px solid rgba(0,0,0,0.05);
    }
    .pricing-card-header.premium {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .price-value {
        font-size: 3rem;
        font-weight: 800;
    }
    .price-period {
        font-size: 1rem;
        opacity: 0.8;
    }
    .pricing-card-body {
        padding: 2rem;
        flex: 1;
    }
    .feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .feature-list li {
        margin-bottom: 1rem;
        color: #555;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
    }
    .feature-list li i {
        color: var(--activation-color, #28a745);
        margin-right: 10px;
        font-size: 1.2rem;
    }
    .btn-plan {
        width: 100%;
        border-radius: 50px;
        padding: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: auto;
    }

    /* --- WhatsApp Float Button --- */
    .whatsapp-float {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 60px;
        height: 60px;
        background: #25D366;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        transition: all 0.3s;
        animation: pulse 2s infinite;
        text-decoration: none;
    }
    .whatsapp-float:hover {
        background: #128C7E;
        color: white;
        transform: scale(1.1);
    }
    @keyframes pulse {
        0%, 100% { box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4); }
        50% { box-shadow: 0 4px 20px rgba(37, 211, 102, 0.7); }
    }

    /* --- Social Links --- */
    .social-links a {
        display: inline-block;
        width: 40px;
        height: 40px;
        background: rgba(255,255,255,0.1);
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        margin: 0 5px;
        transition: all 0.3s;
        text-decoration: none;
    }
    .social-links a:hover {
        background: white;
        color: var(--primary-color);
        transform: translateY(-3px);
    }

    /* --- Footer --- */
    footer {
        background: var(--secondary-color);
        color: white;
        padding: 3rem 0;
        margin-top: 5rem;
    }

    /* --- Responsive Media Queries --- */
    @media (max-width: 768px) {
        /* Carousel en móvil */
        .carousel-item {
            height: 400px;
        }
        .carousel-caption h1 {
            font-size: 2rem;
        }
        .carousel-caption p {
            font-size: 1rem;
        }
        .carousel-caption .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        /* WhatsApp button en móvil */
        .whatsapp-float {
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
        }

        /* Features cards */
        .feature-card {
            padding: 1.5rem;
        }
        .feature-card i {
            font-size: 2.5rem !important;
        }

        /* Testimonials */
        .testimonial-card {
            padding: 1.5rem;
        }

        /* Pricing */
        .pricing-header h2 {
            font-size: 2rem;
        }
        .price-value {
            font-size: 2.5rem;
        }

        /* Navbar */
        .navbar-brand {
            font-size: 1.2rem;
        }
        .btn-login-nav {
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
        }
    }

    @media (min-width: 769px) and (max-width: 992px) {
        /* Tablet adjustments */
        .carousel-item {
            height: 450px;
        }
        .carousel-caption h1 {
            font-size: 2.5rem;
        }
        .carousel-caption p {
            font-size: 1.2rem;
        }
    }
  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand" href="#"><i class="bi bi-rocket-takeoff-fill me-2"></i>SpacePark</a>
    <div class="ms-auto">
      <a href="login.php" class="btn-login-nav">
        <i class="bi bi-person-fill me-2"></i>Iniciar Sesión
      </a>
    </div>
  </div>
</nav>

<!-- Carousel -->
<?php if (!empty($carouselSlides)): ?>
<div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-indicators">
    <?php foreach ($carouselSlides as $index => $slide): ?>
      <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $index ?>" <?= $index === 0 ? 'class="active"' : '' ?>></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner">
    <?php foreach ($carouselSlides as $index => $slide): ?>
      <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
        <?php if ($slide['background_type'] === 'gradient'): ?>
          <div style="width:100%; height:100%; background: linear-gradient(45deg, <?= $slide['gradient_start'] ?>, <?= $slide['gradient_end'] ?>); display:flex; justify-content:center; align-items:center;">
            <?php if ($slide['icon']): ?>
              <i class="bi <?= $slide['icon'] ?>" style="font-size: 15rem; color: rgba(255,255,255,0.1);"></i>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <img src="<?= $slide['image_url'] ?>" alt="<?= htmlspecialchars($slide['title']) ?>">
        <?php endif; ?>
        <div class="carousel-caption d-none d-md-block">
          <h1><?= htmlspecialchars($slide['title']) ?></h1>
          <?php if ($slide['subtitle']): ?>
            <p><?= htmlspecialchars($slide['subtitle']) ?></p>
          <?php endif; ?>
          <?php if ($slide['button_text'] && $slide['button_link']): ?>
            <a href="<?= $slide['button_link'] ?>" class="btn btn-light btn-lg mt-3">
              <?= htmlspecialchars($slide['button_text']) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>
<?php endif; ?>

<!-- Features Section -->
<?php if (!empty($features)): ?>
<section class="features-section py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">¿Por qué elegir SpacePark?</h2>
      <p class="text-muted lead">Características que hacen la diferencia</p>
    </div>
    <div class="row g-4">
      <?php foreach ($features as $feature): ?>
        <div class="col-md-6 col-lg-4">
          <div class="feature-card text-center">
            <i class="bi <?= $feature['icon'] ?>" style="font-size:3rem; color:var(--primary-color);"></i>
            <h4 class="mt-3 mb-3"><?= htmlspecialchars($feature['title']) ?></h4>
            <p class="text-muted"><?= htmlspecialchars($feature['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Pricing Section -->
<div class="container">
    <div class="pricing-header">
        <h2>Planes Flexibles para tu Negocio</h2>
        <p class="text-muted lead">Elige el plan que mejor se adapte a tus necesidades.</p>
    </div>

    <?php if (empty($plans)): ?>
        <div class="alert alert-warning text-center">
            No hay planes activos configurados en este momento. <br>
            <small>Por favor contacte al administrador.</small>
        </div>
    <?php else: ?>
        <div class="row justify-content-center">
            <?php foreach ($plans as $plan): 
                $planFeatures = json_decode($plan['features'] ?? '[]', true) ?? [];
                $isPremium = $plan['price'] >= 10000;
                
                $periodText = [
                    'monthly' => '/mes',
                    'quarterly' => '/trimestre',
                    'annual' => '/año'
                ][$plan['period']] ?? '/año';
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="pricing-card">
                    <div class="pricing-card-header <?= $isPremium ? 'premium':'' ?>">
                        <h4 class="text-uppercase fw-bold m-0"><?= htmlspecialchars($plan['name']) ?></h4>
                        <div class="mt-3">
                            <span class="price-value">$<?= number_format($plan['price'], 0, ',', '.') ?></span>
                            <span class="d-block price-period"><?= $periodText ?></span>
                        </div>
                    </div>
                    <div class="pricing-card-body d-flex flex-column">
                        <ul class="feature-list mb-4">
                            <?php 
                            $posIncluded = $plan['pos_included'] ?? 1;
                            $posMonthly = $plan['pos_extra_monthly_fee'] ?? 500;
                            $posAnnual = $plan['pos_extra_annual_fee'] ?? 5000;
                            ?>
                            <li><i class="bi bi-check-circle-fill"></i> <strong><?= $posIncluded ?> POS Master</strong> incluido</li>
                            <li><i class="bi bi-check-circle-fill"></i> POS adicionales: $<?= number_format($posMonthly, 0) ?>/mes</li>
                            <li><i class="bi bi-check-circle-fill"></i> Pago anual: $<?= number_format($posAnnual, 0) ?>/año</li>
                            <?php foreach($planFeatures as $f): ?>
                                <li><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars(trim($f)) ?></li>
                            <?php endforeach; ?>
                            <?php if(count($planFeatures) < 2): ?>
                                <li><i class="bi bi-check-circle-fill"></i> Soporte 24/7</li>
                                <li><i class="bi bi-check-circle-fill"></i> Actualizaciones</li>
                            <?php endif; ?>
                        </ul>
                        
                        <a href="signup.php?plan_id=<?= $plan['id'] ?>" class="btn btn-lg btn-plan <?= $isPremium ? 'btn-primary' : 'btn-outline-primary' ?>">
                            CONTRATAR AHORA
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="text-center mt-5">
        <p class="text-muted">
            <i class="bi bi-shield-lock-fill me-1"></i> Pagos procesados de forma segura vía Mercado Pago. 
            <br>La activación es automática tras la confirmación del pago.
        </p>
    </div>
</div>

<!-- Testimonials Section -->
<?php if ($testimonialsEnabled && !empty($testimonials)): ?>
<section class="testimonials-section py-5">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="fw-bold">Lo que dicen nuestros clientes</h2>
      <p class="text-muted lead">Testimonios reales de negocios que confían en SpacePark</p>
    </div>
    <div class="row g-4">
      <?php foreach ($testimonials as $test): ?>
        <div class="col-md-6 col-lg-4">
          <div class="testimonial-card">
            <div class="d-flex align-items-center mb-3">
              <?php if ($test['avatar_url']): ?>
                <img src="<?= $test['avatar_url'] ?>" class="rounded-circle me-3" width="60" height="60" alt="Avatar">
              <?php else: ?>
                <i class="bi bi-person-circle me-3 text-muted" style="font-size:3.5rem;"></i>
              <?php endif; ?>
              <div>
                <h5 class="mb-0"><?= htmlspecialchars($test['customer_name']) ?></h5>
                <?php if ($test['business_name']): ?>
                  <small class="text-muted"><?= htmlspecialchars($test['business_name']) ?></small>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-warning mb-2">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star<?= $i <= $test['rating'] ? '-fill' : '' ?>"></i>
              <?php endfor; ?>
            </div>
            <p class="text-muted">"<?= htmlspecialchars($test['testimonial']) ?>"</p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                <h5 class="fw-bold mb-3"><i class="bi bi-rocket-takeoff-fill me-2"></i>SpacePark POS</h5>
                <p class="mb-0">Tu solución completa de punto de venta</p>
                <small class="text-muted">Hecho con <i class="bi bi-heart-fill text-danger"></i> para el comercio moderno.</small>
            </div>
            <div class="col-md-6 text-md-end">
                <h5 class="fw-bold mb-3">Síguenos</h5>
                <div class="social-links">
                    <?php if ($facebookUrl): ?>
                        <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if ($instagramUrl): ?>
                        <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if ($twitterUrl): ?>
                        <a href="<?= htmlspecialchars($twitterUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-twitter-x"></i></a>
                    <?php endif; ?>
                    <?php if ($linkedinUrl): ?>
                        <a href="<?= htmlspecialchars($linkedinUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-linkedin"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Data Fiscal ARCA -->
        <?php if ($arcaEnabled): ?>
        <div class="text-center mt-3 pt-3 border-top border-secondary">
            <a href="<?= htmlspecialchars($arcaQrUrl) ?>" target="_F960AFIPInfo" rel="noopener">
                <img src="<?= htmlspecialchars($arcaImageUrl) ?>" 
                     alt="Data Fiscal ARCA" 
                     border="0"
                     style="max-width: 65px; height: auto; opacity: 0.85;">
            </a>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-4 pt-4 border-top border-secondary">
            <p class="mb-0">&copy; <?= date('Y') ?> SpacePark Systems. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<!-- WhatsApp Floating Button -->
<?php if ($whatsappEnabled): ?>
<a href="https://wa.me/<?= $whatsappNumber ?>?text=<?= urlencode($whatsappMessage) ?>" 
   class="whatsapp-float" target="_blank" rel="noopener" title="Contactar por WhatsApp">
  <i class="bi bi-whatsapp"></i>
</a>
<?php endif; ?>

<!-- Promotional Popup -->
<?php if ($popupEnabled): ?>
<div class="modal fade" id="promoPopup" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= htmlspecialchars($popupTitle) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <?php if ($popupImageUrl): ?>
          <img src="<?= $popupImageUrl ?>" class="img-fluid mb-3 rounded" alt="Promoción">
        <?php endif; ?>
        <div><?= $popupContent ?></div>
      </div>
    </div>
  </div>
</div>

<script>
// Lógica de frecuencia del popup
const popupFrequency = '<?= $popupFrequency ?>';
const storageKey = 'spacepark_popup_shown';

function shouldShowPopup() {
  if (popupFrequency === 'always') return true;
  
  const storage = popupFrequency === 'once_per_session' ? sessionStorage : localStorage;
  const lastShown = storage.getItem(storageKey);
  
  if (!lastShown) return true;
  
  if (popupFrequency === 'once_per_day') {
    const daysSince = (Date.now() - parseInt(lastShown)) / (1000 * 60 * 60 * 24);
    return daysSince >= 1;
  }
  
  return false;
}

if (shouldShowPopup()) {
  setTimeout(() => {
    const modal = new bootstrap.Modal(document.getElementById('promoPopup'));
    modal.show();
    
    const storage = popupFrequency === 'once_per_session' ? sessionStorage : localStorage;
    storage.setItem(storageKey, Date.now().toString());
  }, 2000);
}
</script>
<?php endif; ?>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>