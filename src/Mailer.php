<?php
// src/Mailer.php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Billing.php';

class Mailer {
    public static function sendNow($to, $subject, $body, $headers = []) {
        $from = Billing::getSetting('mail_from') ?? 'no-reply@localhost';
        $hdrs = "From: " . $from . "\r\n";
        foreach ($headers as $k => $v) $hdrs .= "$k: $v\r\n";
        $sent = @mail($to, $subject, $body, $hdrs);
        return (bool)$sent;
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
