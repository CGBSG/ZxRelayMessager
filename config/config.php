<?php
// ===== ZxRelay Configuration =====
// Fill these in with your real hosting/database details.

define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// Base URL of the site (no trailing slash), e.g. https://example.com/zxrelay
define('SITE_URL', 'https://zxrelay.zya.me/');

// Max upload size in bytes (default 50MB)
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024);

// Site name
define('SITE_NAME', 'ZxRelay');

date_default_timezone_set('UTC');

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily while debugging setup issues
