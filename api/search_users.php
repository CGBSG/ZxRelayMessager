<?php
require_once __DIR__ . '/_bootstrap.php';

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) json_out(['ok' => true, 'users' => []]);

$stmt = $pdo->prepare("SELECT id, username, display_name, avatar, bio FROM users
                        WHERE (username LIKE ? OR display_name LIKE ?) AND id != ? AND is_banned = 0
                        LIMIT 20");
$like = '%' . $q . '%';
$stmt->execute([$like, $like, $uid]);
json_out(['ok' => true, 'users' => $stmt->fetchAll()]);
