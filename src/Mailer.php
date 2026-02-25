<?php
// src/Mailer.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Billing.php';

class Mailer {
    public static function sendNow($to, $subject, $body, $headers = []) {
        $db = Database::getInstance()->getConnection();
        
        // 1. Cargar Configuración SMTP
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'mail_%'");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $smtpHost = $settings['smtp_host'] ?? '';
        $smtpPort = $settings['smtp_port'] ?? 587;
        $smtpSecure = $settings['smtp_secure'] ?? 'tls';
        $smtpUsername = $settings['smtp_username'] ?? '';
        $smtpPassword = $settings['smtp_password'] ?? '';
        $mailFrom = $settings['mail_from'] ?? 'no-reply@spacepark.local';
        $mailFromName = $settings['mail_from_name'] ?? 'SpacePark';

        // 2. Intentar cargar PHPMailer
        // Asumimos que vendor está en la raíz del proyecto (un nivel arriba de src/)
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }

        // Verificar si PHPMailer está cargado
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Fallback a mail() si no hay PHPMailer, pero logueando el error
            error_log("SpacePark Mailer: PHPMailer no encontrado. Usando mail() nativo como fallback.");
            return self::sendNativeInfo($to, $subject, $body, $headers, $mailFrom);
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            if ($smtpHost && $smtpUsername && $smtpPassword) {
                $mail->isSMTP();
                $mail->Host       = $smtpHost;
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtpUsername;
                $mail->Password   = $smtpPassword;
                $mail->SMTPSecure = $smtpSecure == 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = (int)$smtpPort;
            } else {
                // Si no hay config SMTP, usa mail() interno de PHPMailer
                $mail->isMail();
            }

            $mail->CharSet = 'UTF-8';

            // Recipients
            $mail->setFrom($mailFrom, $mailFromName);
            $mail->addAddress($to);
            if (isset($headers['Reply-To'])) {
                $mail->addReplyTo($headers['Reply-To']);
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            // Strip tags for non-HTML client fallback
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("SpacePark Mailer Error: " . $e->getMessage());
            return false;
        }
    }

    private static function sendNativeInfo($to, $subject, $body, $headersArray, $from) {
        // Fallback antiguo
        $headers = "From: $from\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        foreach ($headersArray as $k => $v) {
            if ($k !== 'From' && $k !== 'Reply-To') {
                $headers .= "$k: $v\r\n";
            }
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return @mail($to, $subject, $body, $headers);
    }

    public static function queue($to, $subject, $body, $headers = null) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO outbox_emails (`to`, subject, body, headers) VALUES (?, ?, ?, ?)");
        $stmt->execute([$to, $subject, $body, $headers ? json_encode($headers) : null]);
        return $db->lastInsertId();
    }

    public static function processQueue($limit = 20) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM outbox_emails WHERE status = 'pending' ORDER BY created_at LIMIT ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $headers = $r['headers'] ? json_decode($r['headers'], true) : [];
            $ok = self::sendNow($r['to'], $r['subject'], $r['body'], $headers);
            if ($ok) {
                $u = $db->prepare("UPDATE outbox_emails SET status = 'sent', attempts = attempts + 1, last_attempt = " . \Database::nowSql() . " WHERE id = ?");
                $u->execute([$r['id']]);
            } else {
                $u = $db->prepare("UPDATE outbox_emails SET attempts = attempts + 1, last_attempt = " . \Database::nowSql() . " WHERE id = ?");
                $u->execute([$r['id']]);
            }
        }
        return count($rows);
    }
}
