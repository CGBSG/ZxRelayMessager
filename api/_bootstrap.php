<?php
require_once __DIR__ . '/../includes/functions.php';
header('X-Content-Type-Options: nosniff');
$uid = require_login();
touch_last_seen($pdo, $uid);
