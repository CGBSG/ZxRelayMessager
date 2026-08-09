<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$title = trim($_POST['title'] ?? '');
$bio = trim($_POST['bio'] ?? '');
if ($title === '' || mb_strlen($title) > 120) json_out(['ok' => false, 'error' => 'invalid_title'], 400);

$pdo->beginTransaction();
try {
    $code = gen_invite_code();
    $stmt = $pdo->prepare('INSERT INTO chats (type, title, bio, invite_code, owner_id, created_at) VALUES ("channel", ?, ?, ?, ?, NOW())');
    $stmt->execute([$title, $bio, $code, $uid]);
    $chatId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"owner")')->execute([$chatId, $uid]);

    $pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,NULL,"system",?,NOW())')
        ->execute([$chatId, 'کانال ساخته شد']);

    $pdo->commit();
    json_out(['ok' => true, 'chat_id' => $chatId, 'invite_code' => $code]);
} catch (Exception $e) {
    $pdo->rollBack();
    json_out(['ok' => false, 'error' => 'server_error'], 500);
}
