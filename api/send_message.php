<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$replyTo = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;

if ($chatId <= 0 || $content === '') json_out(['ok' => false, 'error' => 'invalid_input'], 400);
if (mb_strlen($content) > 4000) json_out(['ok' => false, 'error' => 'too_long'], 400);

$member = require_membership($pdo, $chatId, $uid);

$chatStmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$chatStmt->execute([$chatId]);
$chat = $chatStmt->fetch();
if (!$chat) json_out(['ok' => false, 'error' => 'no_chat'], 404);

if ($chat['type'] === 'channel' && !in_array($member['role'], ['owner', 'admin'])) {
    json_out(['ok' => false, 'error' => 'channel_readonly'], 403);
}

$stmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, reply_to, type, content, created_at) VALUES (?,?,?,"text",?,NOW())');
$stmt->execute([$chatId, $uid, $replyTo, $content]);
$msgId = (int)$pdo->lastInsertId();

$upd = $pdo->prepare('UPDATE chat_members SET last_read_message_id = ? WHERE chat_id = ? AND user_id = ?');
$upd->execute([$msgId, $chatId, $uid]);

$pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id != ?')->execute([$chatId, $uid]);

$pdo->prepare('DELETE FROM typing_status WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $uid]);

json_out(['ok' => true, 'message_id' => $msgId]);
