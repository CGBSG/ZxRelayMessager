<?php
require_once __DIR__ . '/_bootstrap.php';

$chatId = (int)($_GET['chat_id'] ?? 0);
require_membership($pdo, $chatId, $uid);

$stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen, cm.role
                        FROM chat_members cm JOIN users u ON u.id = cm.user_id
                        WHERE cm.chat_id = ? ORDER BY (cm.role='owner') DESC, (cm.role='admin') DESC, u.display_name ASC");
$stmt->execute([$chatId]);
json_out(['ok' => true, 'members' => $stmt->fetchAll()]);
