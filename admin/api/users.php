<?php
require_once __DIR__ . '/../../includes/functions.php';
require_site_owner($pdo);

$stmt = $pdo->query('SELECT id, username, display_name, bio, avatar, reg_ip, last_ip, os, created_at, last_seen, is_banned, is_owner FROM users ORDER BY id DESC');
json_out(['ok' => true, 'users' => $stmt->fetchAll()]);
