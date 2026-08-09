<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$stickerFile = basename($_POST['sticker'] ?? '');
$allowedDir = realpath(__DIR__ . '/../assets/stickers');
$fullPath = $allowedDir . '/' . $stickerFile;

if ($chatId <= 0 || $stickerFile === '' || !file_exists($fullPath)) {
    json_out(['ok' => false, 'error' => 'invalid_input'], 400);
}

$member = require_membership($pdo, $chatId, $uid);
$chatStmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$chatStmt->execute([$chatId]);
$chat = $chatStmt->fetch();
if ($chat['type'] === 'channel' && !in_array($member['role'], ['owner', 'admin'])) {
    json_out(['ok' => false, 'error' => 'channel_readonly'], 403);
}

$relPath = 'assets/stickers/' . $stickerFile;
$stmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, file_path, created_at) VALUES (?,?,"sticker",?,NOW())');
$stmt->execute([$chatId, $uid, $relPath]);
$msgId = (int)$pdo->lastInsertId();

$upd = $pdo->prepare('UPDATE chat_members SET last_read_message_id = ? WHERE chat_id = ? AND user_id = ?');
$upd->execute([$msgId, $chatId, $uid]);

$pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id != ?')->execute([$chatId, $uid]);

json_out(['ok' => true, 'message_id' => $msgId]);
