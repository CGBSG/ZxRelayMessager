<?php
require_once __DIR__ . '/_bootstrap.php';

$chatId = (int)($_GET['chat_id'] ?? 0);
$beforeId = (int)($_GET['before_id'] ?? 0);
$afterId = (int)($_GET['after_id'] ?? 0);
$limit = 40;

$member = require_membership($pdo, $chatId, $uid);

if ($afterId > 0) {
    $stmt = $pdo->prepare("SELECT m.*, u.display_name AS sender_name, u.avatar AS sender_avatar
                            FROM messages m LEFT JOIN users u ON u.id = m.sender_id
                            WHERE m.chat_id = ? AND m.id > ? ORDER BY m.id ASC LIMIT 200");
    $stmt->execute([$chatId, $afterId]);
    $rows = $stmt->fetchAll();
} elseif ($beforeId > 0) {
    $stmt = $pdo->prepare("SELECT m.*, u.display_name AS sender_name, u.avatar AS sender_avatar
                            FROM messages m LEFT JOIN users u ON u.id = m.sender_id
                            WHERE m.chat_id = ? AND m.id < ? ORDER BY m.id DESC LIMIT $limit");
    $stmt->execute([$chatId, $beforeId]);
    $rows = array_reverse($stmt->fetchAll());
} else {
    $stmt = $pdo->prepare("SELECT m.*, u.display_name AS sender_name, u.avatar AS sender_avatar
                            FROM messages m LEFT JOIN users u ON u.id = m.sender_id
                            WHERE m.chat_id = ? ORDER BY m.id DESC LIMIT $limit");
    $stmt->execute([$chatId]);
    $rows = array_reverse($stmt->fetchAll());
}

// reactions for these messages
$ids = array_column($rows, 'id');
$reactions = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt2 = $pdo->prepare("SELECT * FROM message_reactions WHERE message_id IN ($in)");
    $stmt2->execute($ids);
    foreach ($stmt2->fetchAll() as $r) {
        $reactions[$r['message_id']][] = ['emoji' => $r['emoji'], 'user_id' => (int)$r['user_id']];
    }
}

// reply-to preview text
$replyIds = array_filter(array_column($rows, 'reply_to'));
$replies = [];
if ($replyIds) {
    $in = implode(',', array_fill(0, count($replyIds), '?'));
    $stmt3 = $pdo->prepare("SELECT m.id, m.content, m.type, u.display_name FROM messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.id IN ($in)");
    $stmt3->execute(array_values($replyIds));
    foreach ($stmt3->fetchAll() as $r) {
        $replies[$r['id']] = $r;
    }
}

$out = [];
foreach ($rows as $m) {
    $out[] = [
        'id' => (int)$m['id'],
        'chat_id' => (int)$m['chat_id'],
        'sender_id' => $m['sender_id'] ? (int)$m['sender_id'] : null,
        'sender_name' => $m['sender_name'],
        'sender_avatar' => $m['sender_avatar'],
        'type' => $m['type'],
        'content' => $m['is_deleted'] ? null : $m['content'],
        'file_path' => $m['is_deleted'] ? null : $m['file_path'],
        'file_name' => $m['file_name'],
        'is_deleted' => (bool)$m['is_deleted'],
        'edited' => (bool)$m['edited_at'],
        'created_at' => $m['created_at'],
        'reply_to' => $m['reply_to'] ? (int)$m['reply_to'] : null,
        'reply_preview' => $m['reply_to'] && isset($replies[$m['reply_to']]) ? [
            'sender' => $replies[$m['reply_to']]['display_name'] ?? 'کاربر حذف‌شده',
            'text' => $replies[$m['reply_to']]['type'] === 'text' ? mb_substr($replies[$m['reply_to']]['content'], 0, 80) : '['.$replies[$m['reply_to']]['type'].']',
        ] : null,
        'reactions' => $reactions[$m['id']] ?? [],
        'is_mine' => $m['sender_id'] == $uid,
    ];
}

json_out(['ok' => true, 'messages' => $out, 'my_last_read' => (int)$member['last_read_message_id']]);
