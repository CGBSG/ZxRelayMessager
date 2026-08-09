<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $pdo->prepare("SELECT u.id, u.username, u.display_name, u.avatar, u.bio, u.last_seen
                        FROM contacts c JOIN users u ON u.id = c.contact_id
                        WHERE c.user_id = ? ORDER BY u.display_name ASC");
$stmt->execute([$uid]);
json_out(['ok' => true, 'contacts' => $stmt->fetchAll()]);
