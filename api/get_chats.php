<?php
require_once __DIR__ . '/_bootstrap.php';

$sql = "SELECT c.id, c.type, c.title, c.bio, c.avatar, c.invite_code, c.owner_id,
               cm.role, cm.muted, cm.last_read_message_id,
               (SELECT COUNT(*) FROM chat_members WHERE chat_id = c.id) AS member_count
        FROM chats c
        JOIN chat_members cm ON cm.chat_id = c.id
        WHERE cm.user_id = ? AND cm.is_hidden = 0
        ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uid]);
$chats = $stmt->fetchAll();

$result = [];
foreach ($chats as $c) {
    $title = $c['title'];
    $avatar = $c['avatar'];
    $otherUserId = null;
    $otherOnline = null;

    if ($c['type'] === 'private') {
        $stmt2 = $pdo->prepare("SELECT u.* FROM chat_members cm2 JOIN users u ON u.id = cm2.user_id WHERE cm2.chat_id = ? AND cm2.user_id != ? LIMIT 1");
        $stmt2->execute([$c['id'], $uid]);
        $other = $stmt2->fetch();
        if ($other) {
            $title = $other['display_name'];
            $avatar = $other['avatar'];
            $otherUserId = (int)$other['id'];
            $otherOnline = $other['last_seen'];
        }
    }

    $stmtLast = $pdo->prepare("SELECT m.*, u.display_name AS sender_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id WHERE m.chat_id = ? ORDER BY m.id DESC LIMIT 1");
    $stmtLast->execute([$c['id']]);
    $last = $stmtLast->fetch();

    $stmtUnread = $pdo->prepare("SELECT COUNT(*) AS cnt FROM messages WHERE chat_id = ? AND id > ? AND sender_id != ? AND is_deleted = 0");
    $stmtUnread->execute([$c['id'], $c['last_read_message_id'], $uid]);
    $unread = (int)$stmtUnread->fetch()['cnt'];

    $lastText = '';
    if ($last) {
        if ($last['is_deleted']) $lastText = 'پیام حذف شد';
        elseif ($last['type'] === 'text') $lastText = mb_substr(str_replace("\n", ' ', $last['content']), 0, 60);
        elseif ($last['type'] === 'system') $lastText = $last['content'];
        elseif ($last['type'] === 'image') $lastText = '🖼 عکس';
        elseif ($last['type'] === 'gif') $lastText = '🎞 گیف';
        elseif ($last['type'] === 'sticker') $lastText = '🧩 استیکر';
        elseif ($last['type'] === 'voice') $lastText = '🎤 پیام صوتی';
        elseif ($last['type'] === 'file') $lastText = '📎 ' . ($last['file_name'] ?? 'فایل');
    }

    $result[] = [
        'id' => (int)$c['id'],
        'type' => $c['type'],
        'title' => $title,
        'avatar' => $avatar,
        'bio' => $c['bio'],
        'invite_code' => $c['invite_code'],
        'role' => $c['role'],
        'muted' => (bool)$c['muted'],
        'member_count' => (int)$c['member_count'],
        'other_user_id' => $otherUserId,
        'other_last_seen' => $otherOnline,
        'last_message' => $lastText,
        'last_time' => $last['created_at'] ?? null,
        'last_sender_id' => $last['sender_id'] ?? null,
        'unread_count' => $unread,
        'sort_key' => $last['created_at'] ?? '1970-01-01',
    ];
}

usort($result, fn($a, $b) => strcmp($b['sort_key'], $a['sort_key']));

json_out(['ok' => true, 'chats' => $result]);
