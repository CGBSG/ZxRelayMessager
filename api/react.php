<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$msgId = (int)($_POST['message_id'] ?? 0);
$emoji = trim($_POST['emoji'] ?? '');
$allowed = ['👍','👎','❤️','🔥','😂','😮','😢','🎉'];
if ($msgId <= 0 || !in_array($emoji, $allowed, true)) json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$stmt = $pdo->prepare('SELECT chat_id FROM messages WHERE id = ?');
$stmt->execute([$msgId]);
$msg = $stmt->fetch();
if (!$msg) json_out(['ok' => false, 'error' => 'not_found'], 404);
require_membership($pdo, (int)$msg['chat_id'], $uid);

$stmt2 = $pdo->prepare('SELECT * FROM message_reactions WHERE message_id = ? AND user_id = ?');
$stmt2->execute([$msgId, $uid]);
$existing = $stmt2->fetch();

if ($existing && $existing['emoji'] === $emoji) {
    $pdo->prepare('DELETE FROM message_reactions WHERE id = ?')->execute([$existing['id']]);
} elseif ($existing) {
    $pdo->prepare('UPDATE message_reactions SET emoji = ? WHERE id = ?')->execute([$emoji, $existing['id']]);
} else {
    $pdo->prepare('INSERT INTO message_reactions (message_id, user_id, emoji) VALUES (?,?,?)')->execute([$msgId, $uid, $emoji]);
}

json_out(['ok' => true]);
