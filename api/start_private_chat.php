<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$otherId = (int)($_POST['user_id'] ?? 0);
if ($otherId <= 0 || $otherId === $uid) json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$check->execute([$otherId]);
if (!$check->fetch()) json_out(['ok' => false, 'error' => 'user_not_found'], 404);

// check for existing private chat between the two users
$sql = "SELECT cm1.chat_id FROM chat_members cm1
        JOIN chat_members cm2 ON cm1.chat_id = cm2.chat_id
        JOIN chats c ON c.id = cm1.chat_id
        WHERE c.type = 'private' AND cm1.user_id = ? AND cm2.user_id = ?
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $otherId]);
$existing = $stmt->fetch();

if ($existing) {
    $chatId = (int)$existing['chat_id'];
    // unhide for current user if previously hidden (deleted chat)
    $pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id = ?')->execute([$chatId, $uid]);
} else {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO chats (type, owner_id, created_at) VALUES ("private", NULL, NOW())')->execute();
    $chatId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chatId, $uid]);
    $pdo->prepare('INSERT INTO chat_members (chat_id, user_id, role) VALUES (?,?,"member")')->execute([$chatId, $otherId]);
    $pdo->commit();
    $pdo->prepare('INSERT IGNORE INTO contacts (user_id, contact_id) VALUES (?,?)')->execute([$uid, $otherId]);
}

json_out(['ok' => true, 'chat_id' => $chatId]);
