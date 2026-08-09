<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$targetId = (int)($_POST['user_id'] ?? 0);
$makeAdmin = !empty($_POST['make_admin']);

require_owner_role($pdo, $chatId, $uid);

$stmt = $pdo->prepare('SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ?');
$stmt->execute([$chatId, $targetId]);
$target = $stmt->fetch();
if (!$target || $target['role'] === 'owner') json_out(['ok' => false, 'error' => 'invalid_target'], 400);

$newRole = $makeAdmin ? 'admin' : 'member';
$pdo->prepare('UPDATE chat_members SET role = ? WHERE chat_id = ? AND user_id = ?')->execute([$newRole, $chatId, $targetId]);

json_out(['ok' => true]);
