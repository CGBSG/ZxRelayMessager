<?php
require_once __DIR__ . '/../../includes/functions.php';
require_site_owner($pdo);

$stmt = $pdo->query("SELECT c.id, c.type, c.title, c.invite_code,
                      (SELECT COUNT(*) FROM chat_members WHERE chat_id = c.id) AS member_count
                      FROM chats c WHERE c.type IN ('group','channel') ORDER BY c.id DESC");
json_out(['ok' => true, 'chats' => $stmt->fetchAll()]);
