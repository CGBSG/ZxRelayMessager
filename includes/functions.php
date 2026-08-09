<?php
require_once __DIR__ . '/../config/db.php';

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function require_login(): int {
    $id = current_user_id();
    if (!$id) {
        json_out(['ok' => false, 'error' => 'auth_required'], 401);
    }
    return $id;
}

function require_login_page(): int {
    $id = current_user_id();
    if (!$id) {
        header('Location: index.php');
        exit;
    }
    return $id;
}

function get_user(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function get_user_by_username(PDO $pdo, string $username): ?array {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        json_out(['ok' => false, 'error' => 'bad_csrf'], 403);
    }
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function detect_os(string $ua): string {
    $ua = strtolower($ua);
    if (strpos($ua, 'windows') !== false) return 'Windows';
    if (strpos($ua, 'android') !== false) return 'Android';
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) return 'iOS';
    if (strpos($ua, 'mac os') !== false) return 'macOS';
    if (strpos($ua, 'linux') !== false) return 'Linux';
    return 'Unknown';
}

function touch_last_seen(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('UPDATE users SET last_seen = NOW(), last_ip = ? WHERE id = ?');
    $stmt->execute([client_ip(), $userId]);
}

function gen_invite_code(): string {
    return substr(bin2hex(random_bytes(6)), 0, 10);
}

function is_member(PDO $pdo, int $chatId, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ?');
    $stmt->execute([$chatId, $userId]);
    $m = $stmt->fetch();
    return $m ?: null;
}

function require_membership(PDO $pdo, int $chatId, int $userId): array {
    $m = is_member($pdo, $chatId, $userId);
    if (!$m) json_out(['ok' => false, 'error' => 'not_member'], 403);
    return $m;
}

function require_admin(PDO $pdo, int $chatId, int $userId): array {
    $m = require_membership($pdo, $chatId, $userId);
    if (!in_array($m['role'], ['owner', 'admin'])) {
        json_out(['ok' => false, 'error' => 'not_admin'], 403);
    }
    return $m;
}

function require_owner_role(PDO $pdo, int $chatId, int $userId): array {
    $m = require_membership($pdo, $chatId, $userId);
    if ($m['role'] !== 'owner') {
        json_out(['ok' => false, 'error' => 'not_owner'], 403);
    }
    return $m;
}

function require_site_owner(PDO $pdo): array {
    $id = require_login();
    $u = get_user($pdo, $id);
    if (!$u || !$u['is_owner']) {
        json_out(['ok' => false, 'error' => 'forbidden'], 403);
    }
    return $u;
}

function time_ago_fa(?string $datetime): string {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'همین الان';
    if ($diff < 3600) return floor($diff / 60) . ' دقیقه پیش';
    if ($diff < 86400) return floor($diff / 3600) . ' ساعت پیش';
    if ($diff < 2592000) return floor($diff / 86400) . ' روز پیش';
    return date('Y/m/d', strtotime($datetime));
}

function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $name);
    return substr($name, 0, 150);
}

// Extensions that must NEVER be allowed to execute as scripts on the server.
// Uploaded files always get a safe random name + are served with forced download
// via uploads/.htaccess, but we double-check here too.
function is_dangerous_extension(string $ext): bool {
    $bad = ['php','phtml','php3','php4','php5','php7','phar','pht','cgi','pl','py','sh','exe','asp','aspx','jsp','htaccess','htm','html'];
    return in_array(strtolower($ext), $bad, true);
}
