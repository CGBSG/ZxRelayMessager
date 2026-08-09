<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$code = trim($_POST['code'] ?? '');
if ($code === '') json_out(['ok' => false, 'error' => 'invalid_code'], 400);

$stmt = $pdo->prepare('SELECT * FROM chats WHERE invite_code = ?');
$stmt->execute([$code]);
$chat = $stmt->fetch();
if (!$chat) json_out(['ok' => false, 'error' => 'not_found'], 404);

$existing = is_member($pdo, (int)$chat['id'], $uid);
if ($existing) {
    $pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id = ?')->execute([$chat['id'], $uid]);
    json_out(['ok' => true, 'chat_id' => (int)$chat['id'], 'already_member' => true]);
}

$pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chat['id'], $uid]);

$meInfo = get_user($pdo, $uid);
$label = $chat['type'] === 'channel' ? 'کانال' : 'گروه';
$pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
    ->execute([$chat['id'], $meInfo['display_name'] . ' به ' . $label . ' پیوست']);

json_out(['ok' => true, 'chat_id' => (int)$chat['id'], 'already_member' => false]);
