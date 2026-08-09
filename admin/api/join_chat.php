<?php
require_once __DIR__ . '/../../includes/functions.php';
$owner = require_site_owner($pdo);
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) json_out(['ok' => false, 'error' => 'not_found'], 404);

$existing = is_member($pdo, $chatId, $owner['id']);
if (!$existing) {
    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chatId, $owner['id']]);
    $label = $chat['type'] === 'channel' ? 'کانال' : 'گروه';
    $pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
        ->execute([$chatId, $owner['display_name'] . ' به ' . $label . ' پیوست']);
} else {
    $pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $owner['id']]);
}

json_out(['ok' => true]);
