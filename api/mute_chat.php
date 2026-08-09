<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$muted = !empty($_POST['muted']) ? 1 : 0;
require_membership($pdo, $chatId, $uid);

$pdo->prepare('UPDATE chat_members SET muted = ? WHERE chat_id = ? AND user_id = ?')->execute([$muted, $chatId, $uid]);
json_out(['ok' => true]);
