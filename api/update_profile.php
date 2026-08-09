<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$me = get_user($pdo, $uid);
$fields = [];
$params = [];

if (isset($_POST['display_name'])) {
    $dn = trim($_POST['display_name']);
    if ($dn === '' || mb_strlen($dn) > 64) json_out(['ok' => false, 'error' => 'invalid_name'], 400);
    $fields[] = 'display_name = ?';
    $params[] = $dn;
}

if (isset($_POST['bio'])) {
    $bio = trim($_POST['bio']);
    if (mb_strlen($bio) > 255) json_out(['ok' => false, 'error' => 'bio_too_long'], 400);
    $fields[] = 'bio = ?';
    $params[] = $bio;
}

if (!empty($_POST['username'])) {
    $username = trim($_POST['username']);
    if (!preg_match('/^[a-zA-Z0-9_]{4,32}$/', $username)) json_out(['ok' => false, 'error' => 'invalid_username'], 400);
    if ($username !== $me['username']) {
        $existing = get_user_by_username($pdo, $username);
        if ($existing) json_out(['ok' => false, 'error' => 'username_taken'], 400);
    }
    $fields[] = 'username = ?';
    $params[] = $username;
}

if (!empty($_POST['new_password'])) {
    $current = $_POST['current_password'] ?? '';
    if (!password_verify($current, $me['password_hash'])) {
        json_out(['ok' => false, 'error' => 'wrong_current_password'], 400);
    }
    if (strlen($_POST['new_password']) < 6) json_out(['ok' => false, 'error' => 'password_too_short'], 400);
    $fields[] = 'password_hash = ?';
    $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
}

if (isset($_POST['background'])) {
    $bg = trim($_POST['background']);
    $allowedBg = ['default','purple','green','dark','starry','waves',''];
    if (!in_array($bg, $allowedBg, true)) json_out(['ok' => false, 'error' => 'invalid_background'], 400);
    $fields[] = 'background = ?';
    $params[] = $bg;
}

if (isset($_POST['notif_muted'])) {
    $fields[] = 'notif_muted = ?';
    $params[] = $_POST['notif_muted'] ? 1 : 0;
}

if (!empty($_FILES['avatar']['tmp_name'])) {
    $file = $_FILES['avatar'];
    if ($file['size'] > 5 * 1024 * 1024) json_out(['ok' => false, 'error' => 'avatar_too_large'], 400);
    $mime = mime_content_type($file['tmp_name']);
    if (strpos($mime, 'image/') !== 0) json_out(['ok' => false, 'error' => 'not_image'], 400);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
    $ext = preg_replace('/[^a-z0-9]/', '', $ext);
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/avatars/' . $randomName;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $fields[] = 'avatar = ?';
        $params[] = 'uploads/avatars/' . $randomName;
    }
}

if (empty($fields)) json_out(['ok' => false, 'error' => 'nothing_to_update'], 400);

$params[] = $uid;
$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
$pdo->prepare($sql)->execute($params);

json_out(['ok' => true]);
