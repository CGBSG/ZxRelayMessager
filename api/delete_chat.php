<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
require_membership($pdo, $chatId, $uid);

$pdo->prepare('UPDATE chat_members SET is_hidden = 1 WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $uid]);

json_out(['ok' => true]);
