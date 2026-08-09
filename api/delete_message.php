<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$msgId = (int)($_POST['message_id'] ?? 0);
if ($msgId <= 0) json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$msgId]);
$msg = $stmt->fetch();
if (!$msg) json_out(['ok' => false, 'error' => 'not_found'], 404);

$member = require_membership($pdo, (int)$msg['chat_id'], $uid);
$canDelete = ($msg['sender_id'] == $uid) || in_array($member['role'], ['owner', 'admin']);
if (!$canDelete) json_out(['ok' => false, 'error' => 'forbidden'], 403);

$upd = $pdo->prepare('UPDATE messages SET is_deleted = 1, content = NULL, file_path = NULL WHERE id = ?');
$upd->execute([$msgId]);

json_out(['ok' => true]);
