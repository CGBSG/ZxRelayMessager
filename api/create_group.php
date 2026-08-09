<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$title = trim($_POST['title'] ?? '');
$memberIds = json_decode($_POST['member_ids'] ?? '[]', true);
if (!is_array($memberIds)) $memberIds = [];
if ($title === '' || mb_strlen($title) > 120) json_out(['ok' => false, 'error' => 'invalid_title'], 400);

$pdo->beginTransaction();
try {
    $code = gen_invite_code();
    $stmt = $pdo->prepare('INSERT INTO chats (type, title, invite_code, owner_id, created_at) VALUES ("group", ?, ?, ?, NOW())');
    $stmt->execute([$title, $code, $uid]);
    $chatId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"owner")')->execute([$chatId, $uid]);

    foreach (array_unique($memberIds) as $mid) {
        $mid = (int)$mid;
        if ($mid && $mid !== $uid) {
            $pdo->prepare('INSERT IGNORE INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chatId, $mid]);
        }
    }

    $pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
        ->execute([$chatId, 'گروه ساخته شد']);

    $pdo->commit();
    json_out(['ok' => true, 'chat_id' => $chatId, 'invite_code' => $code]);
} catch (Exception $e) {
    $pdo->rollBack();
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
