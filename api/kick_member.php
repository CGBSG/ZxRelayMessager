<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$targetId = (int)($_POST['user_id'] ?? 0);

$myMembership = require_admin($pdo, $chatId, $uid);

$stmt = $pdo->prepare('SELECT * FROM chat_members WHERE chat_id = ? AND user_id = ?');
$stmt->execute([$chatId, $targetId]);
$target = $stmt->fetch();
if (!$target) json_out(['ok' => false, 'error' => 'not_member'], 404);
if ($target['role'] === 'owner') json_out(['ok' => false, 'error' => 'cannot_kick_owner'], 403);
if ($target['role'] === 'admin' && $myMembership['role'] !== 'owner') json_out(['ok' => false, 'error' => 'only_owner_can_kick_admin'], 403);

$targetUser = get_user($pdo, $targetId);
$pdo->prepare('DELETE FROM chat_members WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $targetId]);
$pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
    ->execute([$chatId, $targetUser['display_name'] . ' توسط ادمین حذف شد']);

json_out(['ok' => true]);
