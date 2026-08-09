<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$msgId = (int)($_POST['message_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
if ($msgId <= 0 || $content === '') json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$stmt = $pdo->prepare('SELECT * FROM messages WHERE id = ?');
$stmt->execute([$msgId]);
$msg = $stmt->fetch();
if (!$msg) json_out(['ok' => false, 'error' => 'not_found'], 404);
if ($msg['sender_id'] != $uid) json_out(['ok' => false, 'error' => 'forbidden'], 403);
if ($msg['type'] !== 'text') json_out(['ok' => false, 'error' => 'not_editable'], 400);

$upd = $pdo->prepare('UPDATE messages SET content = ?, edited_at = NOW() WHERE id = ?');
$upd->execute([$content, $msgId]);

json_out(['ok' => true]);
