<?php
require_once __DIR__ . '/_bootstrap.php';

$chatId = (int)($_REQUEST['chat_id'] ?? 0);
require_membership($pdo, $chatId, $uid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare('INSERT INTO typing_status (chat_id, user_id, updated_at) VALUES (?,?,NOW())
                            ON DUPLICATE KEY UPDATE updated_at = NOW()');
    $stmt->execute([$chatId, $uid]);
    json_out(['ok' => true]);
} else {
    $stmt = $pdo->prepare("SELECT t.user_id, u.display_name FROM typing_status t JOIN users u ON u.id = t.user_id
                            WHERE t.chat_id = ? AND t.user_id != ? AND t.updated_at > (NOW() - INTERVAL 5 SECOND)");
    $stmt->execute([$chatId, $uid]);
    json_out(['ok' => true, 'typing' => $stmt->fetchAll()]);
}
