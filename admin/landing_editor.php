<?php
// admin/landing_editor.php
require_once __DIR__ . '/../src/Database.php';
include 'layout_head.php';

// Solo admin puede acceder
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Obtener datos actuales
$carousel = $db->query("SELECT * FROM landing_carousel ORDER BY display_order ASC")->fetchAll();
$features = $db->query("SELECT * FROM landing_features ORDER BY display_order ASC")->fetchAll();
$testimonials = $db->query("SELECT * FROM landing_testimonials ORDER BY display_order ASC")->fetchAll();

// Obtener settings
$settingsRaw = $db->query("SELECT setting_key, setting_value FROM landing_settings")->fetchAll();
$settings = [];
foreach ($settingsRaw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-globe text-primary"></i> Editor de Landing Page</h2>
                    <p class="text-muted">Gestiona todo el contenido de tu página de inicio</p>
                </div>
                <div>
                    <a href="../landing.php" target="_blank" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i> Vista Previa
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" id="editorTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="carousel-tab" data-bs-toggle="tab" data-bs-target="#carousel" type="button" role="tab">
                <i class="bi bi-images"></i> Carousel
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="features-tab" data-bs-toggle="tab" data-bs-target="#features" type="button" role="tab">
                <i class="bi bi-lightning-charge"></i> Características
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="testimonials-tab" data-bs-toggle="tab" data-bs-target="#testimonials" type="button" role="tab">
                <i class="bi bi-chat-quote"></i> Testimonios
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab">
                <i class="bi bi-gear"></i> Configuración
            </button>
        </li>
    </ul>

    <!-- Tabs Content -->
    <div class="tab-content" id="editorTabsContent">
        
        <!-- ============================================ -->
        <!-- TAB 1: CAROUSEL -->
        <!-- ============================================ -->
        <div class="tab-pane fade show active" id="carousel" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-images"></i> Gestión de Carousel</h5>
                    <button class="btn btn-light btn-sm" onclick="openCarouselModal()">
                        <i class="bi bi-plus-circle"></i> Agregar Slide
                    </button>
                </div>
                <div class="card-body">
                    <div id="carouselList" class="list-group">
                        <?php if (empty($carousel)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No hay slides. Agrega el primero.
                            </div>
                        <?php else: ?>
                            <?php foreach ($carousel as $slide): ?>
                                <div class="list-group-item" data-id="<?= $slide['id'] ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <i class="bi bi-grip-vertical text-muted" style="cursor:move"></i>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($slide['title']) ?></h6>
                                                <small class="text-muted">
                                                    <?= $slide['background_type'] === 'gradient' ? 
                                                        "Gradiente: {$slide['gradient_start']} → {$slide['gradient_end']}" : 
                                                        "Imagen: " . basename($slide['image_url']) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCarousel(<?= $slide['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCarousel(<?= $slide['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" onclick="toggleActive('carousel', <?= $slide['id'] ?>, <?= $slide['active'] ?>)">
                                                <i class="bi bi-<?= $slide['active'] ? 'eye' : 'eye-slash' ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 2: FEATURES -->
        <!-- ============================================ -->
        <div class="tab-pane fade" id="features" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Características del Producto</h5>
                    <button class="btn btn-light btn-sm" onclick="openFeatureModal()">
                        <i class="bi bi-plus-circle"></i> Agregar Característica
                    </button>
                </div>
                <div class="card-body">
                    <div id="featuresList" class="row g-3">
                        <?php if (empty($features)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No hay características. Agrega la primera.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($features as $feature): ?>
                                <div class="col-md-6 col-lg-4" data-id="<?= $feature['id'] ?>">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <i class="bi <?= $feature['icon'] ?> text-success" style="font-size:2rem"></i>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editFeature(<?= $feature['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteFeature(<?= $feature['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <h6><?= htmlspecialchars($feature['title']) ?></h6>
                                            <p class="small text-muted mb-0"><?= htmlspecialchars($feature['description']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 3: TESTIMONIALS -->
        <!-- ============================================ -->
        <div class="tab-pane fade" id="testimonials" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-chat-quote"></i> Testimonios de Clientes</h5>
                    <button class="btn btn-dark btn-sm" onclick="openTestimonialModal()">
                        <i class="bi bi-plus-circle"></i> Agregar Testimonio
                    </button>
                </div>
                <div class="card-body">
                    <!-- Toggle para activar/desactivar sección completa -->
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-info-circle"></i> Mostrar sección de testimonios en la landing</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="testimonialsEnabled" 
                                   <?= ($settings['testimonials_enabled'] ?? '1') == '1' ? 'checked' : '' ?>
                                   onchange="toggleTestimonialsSection(this.checked)">
                        </div>
                    </div>

                    <div id="testimonialsList" class="row g-3">
                        <?php if (empty($testimonials)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No hay testimonios. Agrega el primero.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($testimonials as $test): ?>
                                <div class="col-md-6 col-lg-4" data-id="<?= $test['id'] ?>">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($test['avatar_url']): ?>
                                                        <img src="../<?= $test['avatar_url'] ?>" class="rounded-circle" width="40" height="40" alt="Avatar">
                                                    <?php else: ?>
                                                        <i class="bi bi-person-circle text-muted" style="font-size:2.5rem"></i>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($test['customer_name']) ?></h6>
                                                        <?php if ($test['business_name']): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($test['business_name']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editTestimonial(<?= $test['id'] ?>)">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteTestimonial(<?= $test['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="text-warning mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $test['rating'] ? '-fill' : '' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="small mb-0">"<?= htmlspecialchars($test['testimonial']) ?>"</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- TAB 4: CONFIGURACIÓN -->
        <!-- ============================================ -->
        <div class="tab-pane fade" id="config" role="tabpanel">
            <form id="configForm" onsubmit="saveConfig(event)">
                
                <!-- Maintenance Mode -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="bi bi-tools"></i> Modo Mantenimiento</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get current maintenance status from settings table
                        $stmtMaint = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
                        $stmtMaint->execute(['maintenance_mode']);
                        $maintenanceMode = $stmtMaint->fetchColumn() ?: '0';
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>Estado Actual:</strong>
                                <span id="maintenanceStatus">
                                    <?php if ($maintenanceMode === '1'): ?>
                                        <span class="badge bg-danger fs-6">🔧 ACTIVO - Sitio en mantenimiento</span>
                                    <?php else: ?>
                                        <span class="badge bg-success fs-6">✅ Normal - Sitio funcionando</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <button type="button" id="toggleMaintenance" class="btn btn-warning btn-lg" onclick="toggleMaintenanceMode()">
                                <i class="bi bi-tools"></i> 
                                <?= $maintenanceMode === '1' ? 'Desactivar' : 'Activar' ?> Mantenimiento
                            </button>
                        </div>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Importante:</strong> Cuando actives el modo mantenimiento, los visitantes verán una página "En Construcción" en lugar de la landing normal. El panel de administración seguirá funcionando con normalidad.
                        </div>
                    </div>
                </div>
                
                <!-- WhatsApp -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-whatsapp"></i> Botón Flotante de WhatsApp</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="whatsapp_enabled" name="whatsapp_enabled" 
                                   <?= ($settings['whatsapp_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="whatsapp_enabled">Activar botón de WhatsApp</label>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de WhatsApp</label>
                                <input type="text" class="form-control" name="whatsapp_number" 
                                       value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '541135508224') ?>"
                                       placeholder="541135508224">
                                <small class="text-muted">Incluir código de país sin +</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mensaje Predeterminado</label>
                                <input type="text" class="form-control" name="whatsapp_message" 
                                       value="<?= htmlspecialchars($settings['whatsapp_message'] ?? 'Hola! Quiero información sobre SpacePark POS') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popup -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-window"></i> Popup Promocional</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="popup_enabled" name="popup_enabled" 
                                   <?= ($settings['popup_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="popup_enabled">Activar popup promocional</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Título del Popup</label>
                            <input type="text" class="form-control" name="popup_title" 
                                   value="<?= htmlspecialchars($settings['popup_title'] ?? '¡Oferta Especial!') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contenido (Editor Rico)</label>
                            <textarea id="popup_content" name="popup_content" class="form-control" rows="6"><?= htmlspecialchars($settings['popup_content'] ?? '') ?></textarea>
                            <small class="text-muted">Usa el editor para dar formato al contenido del popup</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Imagen del Popup (opcional)</label>
                            <input type="file" class="form-control" id="popup_image_file" accept="image/*" onchange="uploadPopupImage(this)">
                            <input type="hidden" name="popup_image_url" id="popup_image_url" value="<?= htmlspecialchars($settings['popup_image_url'] ?? '') ?>">
                            <div id="popupImagePreview" class="mt-2">
                                <?php if (!empty($settings['popup_image_url'])): ?>
                                    <img src="../<?= $settings['popup_image_url'] ?>" class="img-thumbnail" style="max-height:100px">
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Máximo 2MB. Formatos: JPG, PNG, GIF, WEBP</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Frecuencia de Visualización</label>
                            <select class="form-select" name="popup_frequency">
                                <option value="always" <?= ($settings['popup_frequency'] ?? '') == 'always' ? 'selected' : '' ?>>Siempre</option>
                                <option value="once_per_session" <?= ($settings['popup_frequency'] ?? 'once_per_session') == 'once_per_session' ? 'selected' : '' ?>>Una vez por sesión</option>
                                <option value="once_per_day" <?= ($settings['popup_frequency'] ?? '') == 'once_per_day' ? 'selected' : '' ?>>Una vez por día</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Redes Sociales -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-share"></i> Redes Sociales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-facebook"></i> Facebook</label>
                                <input type="url" class="form-control" name="facebook_url" 
                                       value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-instagram"></i> Instagram</label>
                                <input type="url" class="form-control" name="instagram_url" 
                                       value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-twitter-x"></i> Twitter/X</label>
                                <input type="url" class="form-control" name="twitter_url" 
                                       value="<?= htmlspecialchars($settings['twitter_url'] ?? '') ?>" placeholder="https://twitter.com/...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><i class="bi bi-linkedin"></i> LinkedIn</label>
                                <input type="url" class="form-control" name="linkedin_url" 
                                       value="<?= htmlspecialchars($settings['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEO -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-search"></i> SEO</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Meta Título</label>
                            <input type="text" class="form-control" name="meta_title" 
                                   value="<?= htmlspecialchars($settings['meta_title'] ?? 'SpacePark - Tu Solución de Punto de Venta') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Descripción</label>
                            <textarea class="form-control" name="meta_description" rows="2"><?= htmlspecialchars($settings['meta_description'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Meta Keywords</label>
                            <input type="text" class="form-control" name="meta_keywords" 
                                   value="<?= htmlspecialchars($settings['meta_keywords'] ?? 'punto de venta, pos, mercado pago') ?>">
                        </div>
                    </div>
                </div>

                <!-- Data Fiscal ARCA -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Data Fiscal ARCA</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="arca_enabled" name="arca_enabled" 
                                   <?= ($settings['arca_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="arca_enabled">
                                <strong>Mostrar Data Fiscal ARCA en el footer</strong>
                            </label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">URL del QR de ARCA</label>
                            <input type="text" class="form-control" name="arca_qr_url" 
                                   value="<?= htmlspecialchars($settings['arca_qr_url'] ?? 'http://qr.arca.gob.ar/?qr=bzxycYWFjNx2rzg0Skbz_g,,') ?>"
                                   placeholder="http://qr.arca.gob.ar/?qr=...">
                            <small class="text-muted">URL del código QR de verificación de ARCA/AFIP</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">URL de la Imagen</label>
                            <input type="text" class="form-control" name="arca_image_url" 
                                   value="<?= htmlspecialchars($settings['arca_image_url'] ?? 'https://www.arca.gob.ar/images/f960/DATAWEB.jpg') ?>"
                                   placeholder="https://www.arca.gob.ar/images/f960/DATAWEB.jpg">
                            <small class="text-muted">URL de la imagen del Data Fiscal (por defecto: imagen oficial de ARCA)</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Vista previa:</strong>
                            <div class="mt-2 text-center">
                                <?php if (!empty($settings['arca_image_url'])): ?>
                                    <a href="<?= htmlspecialchars($settings['arca_qr_url'] ?? '#') ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($settings['arca_image_url']) ?>" 
                                             alt="Data Fiscal ARCA" 
                                             style="max-width: 150px; height: auto; border: 1px solid #ddd;">
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">Configura las URLs para ver la vista previa</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Importante:</strong> 
                            El Data Fiscal es un requerimiento legal en Argentina. Asegúrate de que la URL del QR corresponda a tu CUIT registrado en ARCA/AFIP.
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Modal para Testimonials -->
<div class="modal fade" id="testimonialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="testimonialModalTitle">Agregar Testimonio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="testimonialForm" onsubmit="saveTestimonial(event)">
                <div class="modal-body">
                    <input type="hidden" id="testimonial_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Cliente *</label>
                        <input type="text" class="form-control" id="testimonial_customer_name" name="customer_name" required placeholder="Ej: María González">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Negocio (opcional)</label>
                        <input type="text" class="form-control" id="testimonial_business_name" name="business_name" placeholder="Ej: Kiosco Central">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Testimonio *</label>
                        <textarea class="form-control" id="testimonial_text" name="testimonial" rows="4" required placeholder="Escribe aquí el testimonio del cliente..."></textarea>
                        <small class="text-muted">Máximo 500 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Calificación</label>
                        <div class="rating-selector">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="rating" id="rating1" value="1">
                                <label class="btn btn-outline-warning" for="rating1">⭐</label>
                                
                                <input type="radio" class="btn-check" name="rating" id="rating2" value="2">
                                <label class="btn btn-outline-warning" for="rating2">⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="rating" id="rating3" value="3">
                                <label class="btn btn-outline-warning" for="rating3">⭐⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="rating" id="rating4" value="4">
                                <label class="btn btn-outline-warning" for="rating4">⭐⭐⭐⭐</label>
                                
                                <input type="radio" class="btn-check" name="rating" id="rating5" value="5" checked>
                                <label class="btn btn-outline-warning" for="rating5">⭐⭐⭐⭐⭐</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Avatar (opcional)</label>
                        <input type="file" class="form-control" id="testimonial_avatar" accept="image/*" onchange="uploadTestimonialAvatar(this)">
                        <input type="hidden" id="testimonial_avatar_url" name="avatar_url">
                        <div id="avatarPreview" class="mt-2"></div>
                        <small class="text-muted">Máximo 1MB. Formatos: JPG, PNG, GIF, WEBP</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Features -->
<div class="modal fade" id="featureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="featureModalTitle">Agregar Característica</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="featureForm" onsubmit="saveFeature(event)">
                <div class="modal-body">
                    <input type="hidden" id="feature_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Icono * <span id="iconPreview" class="ms-2"></span></label>
                        <select class="form-select" id="feature_icon" name="icon" required onchange="updateIconPreview()">
                            <option value="">Seleccionar icono...</option>
                            <optgroup label="Velocidad y Rendimiento">
                                <option value="bi-lightning-charge">⚡ Rayo (Velocidad)</option>
                                <option value="bi-speedometer2">🏎️ Velocímetro</option>
                                <option value="bi-rocket-takeoff">🚀 Cohete</option>
                            </optgroup>
                            <optgroup label="Pagos y Dinero">
                                <option value="bi-qr-code-scan">📱 QR Code</option>
                                <option value="bi-credit-card">💳 Tarjeta</option>
                                <option value="bi-cash-coin">💰 Dinero</option>
                                <option value="bi-wallet2">👛 Billetera</option>
                            </optgroup>
                            <optgroup label="Nube y Sincronización">
                                <option value="bi-cloud-arrow-up">☁️ Nube Subida</option>
                                <option value="bi-cloud-check-fill">☁️ Nube Check</option>
                                <option value="bi-cloud-download">☁️ Nube Descarga</option>
                            </optgroup>
                            <optgroup label="Reportes y Análisis">
                                <option value="bi-graph-up-arrow">📈 Gráfico Subida</option>
                                <option value="bi-bar-chart">📊 Gráfico Barras</option>
                                <option value="bi-pie-chart">🥧 Gráfico Torta</option>
                            </optgroup>
                            <optgroup label="Usuarios y Personas">
                                <option value="bi-people-fill">👥 Personas</option>
                                <option value="bi-person-check">✅ Persona Check</option>
                                <option value="bi-person-gear">⚙️ Persona Config</option>
                            </optgroup>
                            <optgroup label="Seguridad">
                                <option value="bi-shield-check">🛡️ Escudo Check</option>
                                <option value="bi-shield-lock">🔒 Escudo Candado</option>
                                <option value="bi-lock-fill">🔐 Candado</option>
                            </optgroup>
                            <optgroup label="Otros">
                                <option value="bi-star-fill">⭐ Estrella</option>
                                <option value="bi-heart-fill">❤️ Corazón</option>
                                <option value="bi-check-circle-fill">✅ Check Circle</option>
                                <option value="bi-gear-fill">⚙️ Engranaje</option>
                            </optgroup>
                        </select>
                        <small class="text-muted">Elige el icono que mejor represente esta característica</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" id="feature_title" name="title" required placeholder="Ej: Rápido y Eficiente">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" id="feature_description" name="description" rows="3" placeholder="Describe brevemente esta característica..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Carousel -->
<div class="modal fade" id="carouselModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="carouselModalTitle">Agregar Slide</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="carouselForm" onsubmit="saveCarousel(event)">
                <div class="modal-body">
                    <input type="hidden" id="carousel_id" name="id">
                    
                    <div class="mb-3">
                        <label class="form-label">Título *</label>
                        <input type="text" class="form-control" id="carousel_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subtítulo</label>
                        <textarea class="form-control" id="carousel_subtitle" name="subtitle" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tipo de Fondo</label>
                        <select class="form-select" id="carousel_bg_type" name="background_type" onchange="toggleBackgroundType()">
                            <option value="gradient">Gradiente</option>
                            <option value="image">Imagen</option>
                        </select>
                    </div>
                    
                    <!-- Gradiente -->
                    <div id="gradientSection" class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Color Inicio</label>
                            <input type="color" class="form-control form-control-color" id="carousel_gradient_start" name="gradient_start" value="#1e3c72">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color Fin</label>
                            <input type="color" class="form-control form-control-color" id="carousel_gradient_end" name="gradient_end" value="#2a5298">
                        </div>
                    </div>
                    
                    <!-- Imagen -->
                    <div id="imageSection" class="mb-3" style="display:none">
                        <label class="form-label">Imagen de Fondo</label>
                        <input type="file" class="form-control" id="carousel_image" accept="image/*" onchange="uploadCarouselImage(this)">
                        <input type="hidden" id="carousel_image_url" name="image_url">
                        <div id="imagePreview" class="mt-2"></div>
                        <small class="text-muted">Máximo 2MB. Formatos: JPG, PNG, GIF, WEBP</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Icono (clase Bootstrap Icons)</label>
                        <input type="text" class="form-control" id="carousel_icon" name="icon" placeholder="bi-star" value="bi-star">
                        <small class="text-muted">Ejemplo: bi-shop-window, bi-qr-code-scan, bi-cloud-check-fill</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Texto del Botón (opcional)</label>
                            <input type="text" class="form-control" id="carousel_button_text" name="button_text" placeholder="Ver Planes">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Link del Botón (opcional)</label>
                            <input type="text" class="form-control" id="carousel_button_link" name="button_link" placeholder="#pricing">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
// ============================================
// CAROUSEL FUNCTIONS
// ============================================

let carouselModal;
let currentCarouselId = null;

document.addEventListener('DOMContentLoaded', function() {
    carouselModal = new bootstrap.Modal(document.getElementById('carouselModal'));
    
    // Inicializar drag & drop para carousel
    const carouselList = document.getElementById('carouselList');
    if (carouselList && carouselList.children.length > 0) {
        new Sortable(carouselList, {
            animation: 150,
            handle: '.bi-grip-vertical',
            onEnd: function() {
                saveCarouselOrder();
            }
        });
    }
});

function openCarouselModal() {
    currentCarouselId = null;
    document.getElementById('carouselForm').reset();
    document.getElementById('carousel_id').value = '';
    document.getElementById('carouselModalTitle').textContent = 'Agregar Slide';
    document.getElementById('imagePreview').innerHTML = '';
    toggleBackgroundType();
    carouselModal.show();
}

function editCarousel(id) {
    currentCarouselId = id;
    
    fetch('api/landing_carousel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get', id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const slide = data.slide;
            document.getElementById('carousel_id').value = slide.id;
            document.getElementById('carousel_title').value = slide.title;
            document.getElementById('carousel_subtitle').value = slide.subtitle || '';
            document.getElementById('carousel_bg_type').value = slide.background_type;
            document.getElementById('carousel_gradient_start').value = slide.gradient_start || '#1e3c72';
            document.getElementById('carousel_gradient_end').value = slide.gradient_end || '#2a5298';
            document.getElementById('carousel_image_url').value = slide.image_url || '';
            document.getElementById('carousel_icon').value = slide.icon || 'bi-star';
            document.getElementById('carousel_button_text').value = slide.button_text || '';
            document.getElementById('carousel_button_link').value = slide.button_link || '';
            
            if (slide.image_url) {
                document.getElementById('imagePreview').innerHTML = 
                    `<img src="../${slide.image_url}" class="img-thumbnail" style="max-height:100px">`;
            }
            
            document.getElementById('carouselModalTitle').textContent = 'Editar Slide';
            toggleBackgroundType();
            carouselModal.show();
        }
    });
}

function saveCarousel(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        action: currentCarouselId ? 'update' : 'create',
        title: formData.get('title'),
        subtitle: formData.get('subtitle'),
        background_type: formData.get('background_type'),
        gradient_start: formData.get('gradient_start'),
        gradient_end: formData.get('gradient_end'),
        image_url: formData.get('image_url'),
        icon: formData.get('icon'),
        button_text: formData.get('button_text'),
        button_link: formData.get('button_link')
    };
    
    if (currentCarouselId) {
        data.id = currentCarouselId;
    }
    
    fetch('api/landing_carousel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            carouselModal.hide();
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function deleteCarousel(id) {
    if (!confirm('¿Estás seguro de eliminar este slide?')) return;
    
    fetch('api/landing_carousel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function toggleActive(type, id, current) {
    fetch(`api/landing_${type}.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'toggle', id: id})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function toggleBackgroundType() {
    const type = document.getElementById('carousel_bg_type').value;
    document.getElementById('gradientSection').style.display = type === 'gradient' ? 'flex' : 'none';
    document.getElementById('imageSection').style.display = type === 'image' ? 'block' : 'none';
}

function uploadCarouselImage(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('image', input.files[0]);
    
    fetch('api/landing_carousel.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            document.getElementById('carousel_image_url').value = result.url;
            document.getElementById('imagePreview').innerHTML = 
                `<img src="../${result.url}" class="img-thumbnail" style="max-height:100px">`;
            alert('✅ Imagen subida correctamente');
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function saveCarouselOrder() {
    const items = document.querySelectorAll('#carouselList .list-group-item');
    const order = Array.from(items).map(item => parseInt(item.dataset.id));
    
    fetch('api/landing_carousel.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'reorder', order: order})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            console.log('Orden actualizado');
        }
    });
}

// ============================================
// FEATURES FUNCTIONS
// ============================================

let featureModal;
let currentFeatureId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modal de features
    if (document.getElementById('featureModal')) {
        featureModal = new bootstrap.Modal(document.getElementById('featureModal'));
    }
    
    // Inicializar drag & drop para features
    const featuresList = document.getElementById('featuresList');
    if (featuresList && featuresList.children.length > 0) {
        new Sortable(featuresList, {
            animation: 150,
            onEnd: function() {
                saveFeaturesOrder();
            }
        });
    }
});

function openFeatureModal() {
    currentFeatureId = null;
    document.getElementById('featureForm').reset();
    document.getElementById('feature_id').value = '';
    document.getElementById('featureModalTitle').textContent = 'Agregar Característica';
    document.getElementById('iconPreview').innerHTML = '';
    featureModal.show();
}

function editFeature(id) {
    currentFeatureId = id;
    
    fetch('api/landing_features.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get', id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const feature = data.feature;
            document.getElementById('feature_id').value = feature.id;
            document.getElementById('feature_icon').value = feature.icon;
            document.getElementById('feature_title').value = feature.title;
            document.getElementById('feature_description').value = feature.description || '';
            
            updateIconPreview();
            document.getElementById('featureModalTitle').textContent = 'Editar Característica';
            featureModal.show();
        }
    });
}

function saveFeature(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        action: currentFeatureId ? 'update' : 'create',
        icon: formData.get('icon'),
        title: formData.get('title'),
        description: formData.get('description')
    };
    
    if (currentFeatureId) {
        data.id = currentFeatureId;
    }
    
    fetch('api/landing_features.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            featureModal.hide();
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function deleteFeature(id) {
    if (!confirm('¿Estás seguro de eliminar esta característica?')) return;
    
    fetch('api/landing_features.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function updateIconPreview() {
    const iconClass = document.getElementById('feature_icon').value;
    const preview = document.getElementById('iconPreview');
    if (iconClass) {
        preview.innerHTML = `<i class="bi ${iconClass}" style="font-size:1.5rem; color:#198754"></i>`;
    } else {
        preview.innerHTML = '';
    }
}

function saveFeaturesOrder() {
    const items = document.querySelectorAll('#featuresList > div[data-id]');
    const order = Array.from(items).map(item => parseInt(item.dataset.id));
    
    fetch('api/landing_features.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'reorder', order: order})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            console.log('Orden de features actualizado');
        }
    });
}

// ============================================
// TESTIMONIALS FUNCTIONS
// ============================================

let testimonialModal;
let currentTestimonialId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar modal de testimonials
    if (document.getElementById('testimonialModal')) {
        testimonialModal = new bootstrap.Modal(document.getElementById('testimonialModal'));
    }
    
    // Inicializar drag & drop para testimonials
    const testimonialsList = document.getElementById('testimonialsList');
    if (testimonialsList && testimonialsList.children.length > 0) {
        new Sortable(testimonialsList, {
            animation: 150,
            onEnd: function() {
                saveTestimonialsOrder();
            }
        });
    }
});

function openTestimonialModal() {
    currentTestimonialId = null;
    document.getElementById('testimonialForm').reset();
    document.getElementById('testimonial_id').value = '';
    document.getElementById('testimonialModalTitle').textContent = 'Agregar Testimonio';
    document.getElementById('avatarPreview').innerHTML = '';
    // Seleccionar 5 estrellas por defecto
    document.getElementById('rating5').checked = true;
    testimonialModal.show();
}

function editTestimonial(id) {
    currentTestimonialId = id;
    
    fetch('api/landing_testimonials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get', id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const test = data.testimonial;
            document.getElementById('testimonial_id').value = test.id;
            document.getElementById('testimonial_customer_name').value = test.customer_name;
            document.getElementById('testimonial_business_name').value = test.business_name || '';
            document.getElementById('testimonial_text').value = test.testimonial;
            document.getElementById('testimonial_avatar_url').value = test.avatar_url || '';
            
            // Seleccionar rating
            const ratingId = 'rating' + test.rating;
            if (document.getElementById(ratingId)) {
                document.getElementById(ratingId).checked = true;
            }
            
            // Mostrar avatar si existe
            if (test.avatar_url) {
                document.getElementById('avatarPreview').innerHTML = 
                    `<img src="../${test.avatar_url}" class="rounded-circle" width="60" height="60" alt="Avatar">`;
            }
            
            document.getElementById('testimonialModalTitle').textContent = 'Editar Testimonio';
            testimonialModal.show();
        }
    });
}

function saveTestimonial(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        action: currentTestimonialId ? 'update' : 'create',
        customer_name: formData.get('customer_name'),
        business_name: formData.get('business_name'),
        testimonial: formData.get('testimonial'),
        rating: formData.get('rating'),
        avatar_url: formData.get('avatar_url')
    };
    
    if (currentTestimonialId) {
        data.id = currentTestimonialId;
    }
    
    fetch('api/landing_testimonials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            testimonialModal.hide();
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function deleteTestimonial(id) {
    if (!confirm('¿Estás seguro de eliminar este testimonio?')) return;
    
    fetch('api/landing_testimonials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ ' + result.message);
            location.reload();
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function uploadTestimonialAvatar(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('avatar', input.files[0]);
    
    fetch('api/landing_testimonials.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            document.getElementById('testimonial_avatar_url').value = result.url;
            document.getElementById('avatarPreview').innerHTML = 
                `<img src="../${result.url}" class="rounded-circle" width="60" height="60" alt="Avatar">`;
            alert('✅ Avatar subido correctamente');
        } else {
            alert('❌ Error: ' + result.error);
        }
    });
}

function saveTestimonialsOrder() {
    const items = document.querySelectorAll('#testimonialsList > div[data-id]');
    const order = Array.from(items).map(item => parseInt(item.dataset.id));
    
    fetch('api/landing_testimonials.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'reorder', order: order})
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            console.log('Orden de testimonios actualizado');
        }
    });
}

// ============================================
// SETTINGS FUNCTIONS
// ============================================
function toggleTestimonialsSection(enabled) {
    fetch('api/landing_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'update',
            key: 'testimonials_enabled',
            value: enabled ? '1' : '0'
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Configuración actualizada');
        }
    });
}

// Inicializar TinyMCE para el contenido del popup
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('popup_content')) {
        tinymce.init({
            selector: '#popup_content',
            apiKey: '2wsqptuiu0r41tidicki0aovob7ozv0wh0qyxbvyt79nddap',
            height: 300,
            menubar: false,
            plugins: 'lists link image code',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
            language: 'es'
        });
    }
});

function uploadPopupImage(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    
    // Validar tamaño (2MB)
    if (file.size > 2 * 1024 * 1024) {
        alert('❌ El archivo es muy grande. Máximo 2MB');
        input.value = '';
        return;
    }
    
    const formData = new FormData();
    formData.append('image', file);
    
    fetch('api/landing_settings.php?action=upload_popup_image', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            document.getElementById('popup_image_url').value = result.url;
            document.getElementById('popupImagePreview').innerHTML = 
                `<img src="../${result.url}" class="img-thumbnail" style="max-height:100px">`;
            alert('✅ Imagen subida correctamente');
        } else {
            alert('❌ Error: ' + result.error);
        }
    })
    .catch(err => {
        alert('❌ Error al subir imagen: ' + err.message);
    });
}

function saveConfig(e) {
    e.preventDefault();
    
    // Sincronizar contenido de TinyMCE antes de guardar
    if (tinymce.get('popup_content')) {
        tinymce.get('popup_content').save();
    }
    
    const formData = new FormData(e.target);
    const data = {};
    
    // Convertir checkboxes a 1/0
    data.whatsapp_enabled = formData.get('whatsapp_enabled') ? '1' : '0';
    data.popup_enabled = formData.get('popup_enabled') ? '1' : '0';
    
    // Resto de campos
    for (let [key, value] of formData.entries()) {
        if (key !== 'whatsapp_enabled' && key !== 'popup_enabled') {
            data[key] = value;
        }
    }
    
    fetch('api/landing_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save_all',
            settings: data
        })
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('✅ Configuración guardada correctamente');
        } else {
            alert('❌ Error: ' + (result.error || 'Desconocido'));
        }
    })
    .catch(err => {
        alert('❌ Error de conexión: ' + err.message);
    });
}

// ============================================
// MAINTENANCE MODE TOGGLE
// ============================================
function toggleMaintenanceMode() {
    const currentMode = <?= json_encode($maintenanceMode ?? '0') ?>;
    const newMode = currentMode === '1' ? '0' : '1';
    const action = newMode === '1' ? 'ACTIVAR' : 'DESACTIVAR';
    
    if (!confirm(`¿Estás seguro de ${action} el modo mantenimiento?\n\n${newMode === '1' ? '⚠️ Los visitantes verán la página "En Construcción"' : '✅ La landing volverá a estar disponible públicamente'}`)) {
        return;
    }
    
    fetch('api/settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'set',
            key: 'maintenance_mode',
            value: newMode
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Modo mantenimiento ${action}DO exitosamente`);
            location.reload();
        } else {
            alert('❌ Error al cambiar modo mantenimiento: ' + (data.error || 'Desconocido'));
        }
    })
    .catch(err => {
        alert('❌ Error de conexión: ' + err.message);
    });
}
</script>

<?php include 'layout_footer.php'; ?>
