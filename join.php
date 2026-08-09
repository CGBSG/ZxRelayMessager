<?php
require_once __DIR__ . '/includes/functions.php';

$code = trim($_GET['code'] ?? '');
if ($code === '') { header('Location: index.php'); exit; }

if (!current_user_id()) {
    $_SESSION['pending_join_code'] = $code;
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM chats WHERE invite_code = ?');
$stmt->execute([$code]);
$chat = $stmt->fetch();
if (!$chat) {
    echo 'لینک دعوت نامعتبر است یا منقضی شده.';
    exit;
}

$uid = current_user_id();
$existing = is_member($pdo, (int)$chat['id'], $uid);
if (!$existing) {
    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chat['id'], $uid]);
    $meInfo = get_user($pdo, $uid);
    $label = $chat['type'] === 'channel' ? 'کانال' : 'گروه';
    $pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
        ->execute([$chat['id'], $meInfo['display_name'] . ' به ' . $label . ' پیوست']);
} else {
    $pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id = ?')->execute([$chat['id'], $uid]);
}

header('Location: app.php?open=' . (int)$chat['id']);
exit;
