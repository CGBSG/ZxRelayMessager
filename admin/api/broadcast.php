<?php
require_once __DIR__ . '/../../includes/functions.php';
$owner = require_site_owner($pdo);
csrf_check();

$text = trim($_POST['text'] ?? '');
if ($text === '') json_out(['ok' => false, 'error' => 'empty_text'], 400);

// Ensure a dedicated "ZxRelay" broadcast channel exists, owned by the site owner.
$stmt = $pdo->prepare("SELECT * FROM chats WHERE type = 'channel' AND title = 'اطلاعیه‌های ZxRelay' LIMIT 1");
$stmt->execute();
$bcChat = $stmt->fetch();

if (!$bcChat) {
    $code = gen_invite_code();
    $pdo->prepare('INSERT INTO chats (type, title, bio, invite_code, owner_id, created_at) VALUES ("channel","اطلاعیه‌های ZxRelay","پیام‌های رسمی مالک پروژه ZxRelay", ?, ?, NOW())')
        ->execute([$code, $owner['id']]);
    $chatId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"owner")')->execute([$chatId, $owner['id']]);
} else {
    $chatId = (int)$bcChat['id'];
}

// Add every user (except already-members) to the broadcast channel so they receive it.
$allUsers = $pdo->query('SELECT id FROM users WHERE is_banned = 0')->fetchAll();
foreach ($allUsers as $u) {
    $pdo->prepare('INSERT IGNORE INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chatId, $u['id']]);
    $pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $u['id']]);
}

$pdo->prepare('INSERT INTO messages (chat_id, sender_id, type, content, created_at) VALUES (?,?,"text",?,NOW())')
    ->execute([$chatId, $owner['id'], $text]);

json_out(['ok' => true, 'chat_id' => $chatId, 'recipients' => count($allUsers)]);
