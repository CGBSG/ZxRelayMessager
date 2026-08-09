<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) json_out(['ok' => false, 'error' => 'not_found'], 404);
if ($chat['type'] === 'private') json_out(['ok' => false, 'error' => 'cannot_leave_private'], 400);

$member = require_membership($pdo, $chatId, $uid);
$meInfo = get_user($pdo, $uid);

$pdo->prepare('DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $uid]);

$label = $chat['type'] === 'channel' ? 'کانال' : 'گروه';
$pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
    ->execute([$chatId, $meInfo['display_name'] . ' از ' . $label . ' خارج شد']);

// if owner left and members remain, promote the oldest admin/member to owner
if ($member['role'] === 'owner') {
    $remain = $pdo->prepare("SELECT * FROM chat_members WHERE chat_id = ? ORDER BY (role='admin') DESC, joined_at ASC LIMIT 1");
    $remain->execute([$chatId]);
    $next = $remain->fetch();
    if ($next) {
        $pdo->prepare('UPDATE chat_members SET role = "owner" WHERE id = ?')->execute([$next['id']]);
        $pdo->prepare('UPDATE chats SET owner_id = ? WHERE id = ?')->execute([$next['user_id'], $chatId]);
    }
}

json_out(['ok' => true]);
