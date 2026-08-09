<?php
// ===== ZxRelay Configuration =====
// Fill these in with your real hosting/database details.

define('DB_HOST', 'sql212.ezyro.com');
define('DB_NAME', 'ezyro_42330729_zxrmsger');
define('DB_USER', 'ezyro_42330729');
define('DB_PASS', '65c2582');

// Base URL of the site (no trailing slash), e.g. https://example.com/zxrelay
define('SITE_URL', 'http://czrat.liveblog365.com/ZxRelay');

// One-time secret key used by /setup_owner.php to promote an account to Owner.
// Change this to your own random string before uploading.
define('SETUP_KEY', 'change-this-secret-key-123');

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
