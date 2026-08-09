<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$upTo = (int)($_POST['message_id'] ?? 0);
require_membership($pdo, $chatId, $uid);

$stmt = $pdo->prepare('UPDATE chat_members SET last_read_message_id = GREATEST(last_read_message_id, ?) WHERE chat_id = ? AND user_id = ?');
$stmt->execute([$upTo, $chatId, $uid]);

json_out(['ok' => true]);
