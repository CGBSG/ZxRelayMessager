<?php
require_once __DIR__ . '/_bootstrap.php';

$chatId = (int)($_GET['chat_id'] ?? 0);
$member = require_membership($pdo, $chatId, $uid);

$stmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$stmt->execute([$chatId]);
$chat = $stmt->fetch();
if (!$chat) json_out(['ok' => false, 'error' => 'not_found'], 404);

$title = $chat['title'];
$avatar = $chat['avatar'];
$bio = $chat['bio'];
$otherUser = null;

if ($chat['type'] === 'private') {
    $stmt2 = $pdo->prepare("SELECT u.* FROM chat_members cm JOIN users u ON u.id = cm.user_id WHERE cm.chat_id = ? AND cm.user_id != ?");
    $stmt2->execute([$chatId, $uid]);
    $other = $stmt2->fetch();
    if ($other) {
        $title = $other['display_name'];
        $avatar = $other['avatar'];
        $bio = $other['bio'];
        $otherUser = ['id' => (int)$other['id'], 'username' => $other['username'], 'last_seen' => $other['last_seen']];
    }
}

$countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM chat_members WHERE chat_id = ?');
$countStmt->execute([$chatId]);
$memberCount = (int)$countStmt->fetch()['c'];

json_out(['ok' => true, 'chat' => [
    'id' => (int)$chat['id'],
    'type' => $chat['type'],
    'title' => $title,
    'avatar' => $avatar,
    'bio' => $bio,
    'invite_code' => $chat['invite_code'],
    'my_role' => $member['role'],
    'muted' => (bool)$member['muted'],
    'member_count' => $memberCount,
    'other_user' => $otherUser,
]]);
