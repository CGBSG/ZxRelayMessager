<?php
require_once __DIR__ . '/_bootstrap.php';

$sinceId = (int)($_GET['since_id'] ?? 0);

$sql = "SELECT m.id, m.chat_id, m.type, m.content, m.sender_id, u.display_name AS sender_name,
               c.type AS chat_type, c.title AS chat_title, cm.muted
        FROM messages m
        JOIN chats c ON c.id = m.chat_id
        JOIN chat_members cm ON cm.chat_id = m.chat_id AND cm.user_id = ?
        LEFT JOIN users u ON u.id = m.sender_id
        WHERE m.id > ? AND m.sender_id != ? AND m.is_deleted = 0 AND m.type != 'system'
        ORDER BY m.id ASC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid, $sinceId, $uid]);
$rows = $stmt->fetchAll();

$me = get_user($pdo, $uid);
$out = [];
foreach ($rows as $r) {
    if ($me['notif_muted'] || $r['muted']) continue;
    $preview = match($r['type']) {
        'text' => mb_substr(str_replace("\n"," ",$r['content']), 0, 100),
        'image' => '🖼 عکس',
        'gif' => '🎞 گیف',
        'sticker' => '🧩 استیکر',
        'voice' => '🎤 پیام صوتی',
        'file' => '📎 فایل',
        default => 'پیام جدید',
    };
    $title = $r['chat_type'] === 'private' ? $r['sender_name'] : ($r['chat_title'] . ' - ' . $r['sender_name']);
    $out[] = ['chat_id' => (int)$r['chat_id'], 'title' => $title, 'body' => $preview, 'id' => (int)$r['id']];
}

$maxId = $rows ? (int)end($rows)['id'] : $sinceId;
json_out(['ok' => true, 'notifications' => $out, 'max_id' => $maxId]);
