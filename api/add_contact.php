<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$contactId = (int)($_POST['user_id'] ?? 0);
if ($contactId <= 0 || $contactId === $uid) json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$check->execute([$contactId]);
if (!$check->fetch()) json_out(['ok' => false, 'error' => 'user_not_found'], 404);

$pdo->prepare('INSERT IGNORE INTO contacts (user_id, contact_id) VALUES (?,?)')->execute([$uid, $contactId]);
json_out(['ok' => true]);
