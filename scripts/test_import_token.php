<?php
// scripts/test_import_token.php
$_POST['sync_token'] = bin2hex(random_bytes(16));
$_POST['allowed_host'] = 'sulocal.tevsko.com.ar';
require __DIR__ . '/../api/import_token.php';
