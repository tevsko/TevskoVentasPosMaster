<?php
// email_worker.php - process outbox_emails
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Mailer.php';
$db = Database::getInstance()->getConnection();
$count = Mailer::processQueue(50);
echo "Processed $count emails.\n";
?>