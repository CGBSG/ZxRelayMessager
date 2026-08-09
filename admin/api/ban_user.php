<?php
require_once __DIR__ . '/../../includes/functions.php';
$owner = require_site_owner($pdo);
csrf_check();

$targetId = (int)($_POST['user_id'] ?? 0);
$banned = !empty($_POST['banned']) ? 1 : 0;

if ($targetId === $owner['id']) json_out(['ok' => false, 'error' => 'cannot_ban_self'], 400);

$pdo->prepare('UPDATE users SET is_banned = ? WHERE id = ?')->execute([$banned, $targetId]);
json_out(['ok' => true]);
