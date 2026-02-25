<?php
// forgot_password.php - Password recovery for admin/client users ONLY
require_once __DIR__ . '/bootstrap_error_handler.php';
require_once 'config/db.php';
require_once 'src/Database.php';
require_once 'src/Auth.php';

// Block access from local SQLite environment (only relevant for cloud)
$driver = Database::getInstance()->getDriver();

$auth = new Auth();
if ($auth->isAuthenticated()) {
    header('Location: admin/dashboard.php');
    exit;
}

$message = '';
$messageType = 'info';
$sent = false;

// Identify tenant from URL/subdomain
$db = Database::getInstance()->getConnection();
$tenantId = null;
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($driver !== 'sqlite') {
    $hostParts = explode('.', $host);
    $hostPart = $hostParts[0];
    if ($hostPart && $hostPart !== 'www') {
        $stmtT = $db->prepare("SELECT id FROM tenants WHERE subdomain = ?");
        $stmtT->execute([$hostPart]);
        $tenantId = $stmtT->fetchColumn();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = 'Por favor ingrese su correo electrónico.';
        $messageType = 'danger';
    } else {
        // Find user by email, role must be admin or branch_manager – NOT a regular employee
        $sql = "SELECT id, emp_name, username, role FROM users WHERE emp_email = ? AND role IN ('admin', 'branch_manager') AND active = 1";
        $params = [$email];
        if ($tenantId && $driver !== 'sqlite') {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Always show success message to avoid user enumeration attacks
        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Ensure table exists (auto-create for cloud)
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(64) NOT NULL,
                    token VARCHAR(64) NOT NULL UNIQUE,
                    expires_at DATETIME NOT NULL,
                    used TINYINT DEFAULT 0
                )");
            } catch (Exception $e) {}

            // Invalidate previous tokens for this user
            $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ?")->execute([$user['id']]);

            // Insert new token
            $db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
               ->execute([$user['id'], $token, $expiresAt]);

            // Build reset link
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $resetUrl = "$protocol://$host/reset_password.php?token=$token";

            // Send email via PHPMailer
            $smtpHost     = $db->query("SELECT setting_value FROM settings WHERE setting_key='smtp_host'")->fetchColumn();
            $smtpPort     = $db->query("SELECT setting_value FROM settings WHERE setting_key='smtp_port'")->fetchColumn() ?: 587;
            $smtpSecure   = $db->query("SELECT setting_value FROM settings WHERE setting_key='smtp_secure'")->fetchColumn() ?: 'tls';
            $smtpUser     = $db->query("SELECT setting_value FROM settings WHERE setting_key='smtp_username'")->fetchColumn();
            $smtpPass     = $db->query("SELECT setting_value FROM settings WHERE setting_key='smtp_password'")->fetchColumn();
            $mailFrom     = $db->query("SELECT setting_value FROM settings WHERE setting_key='mail_from'")->fetchColumn();
            $mailFromName = $db->query("SELECT setting_value FROM settings WHERE setting_key='mail_from_name'")->fetchColumn() ?: 'SpacePark';

            $emailSent = false;
            if ($smtpHost && $smtpUser && $smtpPass) {
                try {
                    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
                    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
                    require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $smtpHost;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpUser;
                    $mail->Password   = $smtpPass;
                    $mail->SMTPSecure = $smtpSecure === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMIME : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = (int)$smtpPort;
                    $mail->CharSet    = 'UTF-8';
                    $mail->setFrom($mailFrom ?: $smtpUser, $mailFromName);
                    $mail->addAddress($email, $user['emp_name'] ?: $user['username']);
                    $mail->isHTML(true);
                    $mail->Subject = '🔐 Recuperación de Contraseña - SpacePark';
                    $mail->Body    = "
                    <div style='font-family:Segoe UI,sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e0e0e0;border-radius:12px'>
                        <h2 style='color:#1e3c72'>🚀 SpacePark</h2>
                        <p>Hola <strong>" . htmlspecialchars($user['emp_name'] ?: $user['username']) . "</strong>,</p>
                        <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
                        <p>Hacé clic en el botón para crear una nueva contraseña. <strong>Este link expira en 1 hora.</strong></p>
                        <p style='text-align:center'>
                            <a href='$resetUrl' style='display:inline-block;background:#1e3c72;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold'>
                                Restablecer mi Contraseña
                            </a>
                        </p>
                        <p style='color:#999;font-size:12px'>Si no solicitaste este cambio, podés ignorar este correo.</p>
                        <hr>
                        <p style='color:#bbb;font-size:11px;text-align:center'>SpacePark · Sistema de Gestión</p>
                    </div>";
                    $mail->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    // Silent fail - still show success to avoid enumeration
                }
            }
        }

        $message = '✅ Si tu correo está registrado, recibirás un email con las instrucciones en los próximos minutos. Revisá también tu carpeta de spam.';
        $messageType = 'success';
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - SpacePark</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/favicon_astronaut.png">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card-box {
            background: rgba(255,255,255,0.97);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 420px;
        }
        .form-control { border-radius: 25px; padding: 10px 20px; }
        .btn-main { border-radius: 25px; padding: 10px; background: #1e3c72; border: none; width: 100%; font-weight: bold; margin-top: 1rem; }
        .btn-main:hover { background: #2a5298; }
    </style>
</head>
<body>
<div class="card-box">
    <div class="text-center mb-4">
        <h2 class="fw-bold" style="color:#1e3c72">SpacePark</h2>
        <p class="text-muted">Recuperar Contraseña</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!$sent): ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label ms-2 text-muted small">Correo Electrónico</label>
            <input type="email" name="email" class="form-control" placeholder="tu@correo.com" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary btn-main">
            <i class="bi bi-send me-2"></i> Enviar Instrucciones
        </button>
    </form>
    <?php endif; ?>

    <div class="text-center mt-3">
        <a href="login.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Volver al Login</a>
    </div>
</div>
</body>
</html>
