<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
require_admin($pdo, $chatId, $uid);

$fields = [];
$params = [];

if (isset($_POST['title'])) {
    $title = trim($_POST['title']);
    if ($title === '' || mb_strlen($title) > 120) json_out(['ok' => false, 'error' => 'invalid_title'], 400);
    $fields[] = 'title = ?';
    $params[] = $title;
}
if (isset($_POST['bio'])) {
    $bio = trim($_POST['bio']);
    if (mb_strlen($bio) > 255) json_out(['ok' => false, 'error' => 'bio_too_long'], 400);
    $fields[] = 'bio = ?';
    $params[] = $bio;
}
if (!empty($_FILES['avatar']['tmp_name'])) {
    $file = $_FILES['avatar'];
    if ($file['size'] > 5 * 1024 * 1024) json_out(['ok' => false, 'error' => 'avatar_too_large'], 400);
    $mime = mime_content_type($file['tmp_name']);
    if (strpos($mime, 'image/') !== 0) json_out(['ok' => false, 'error' => 'not_image'], 400);
    $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) ?: 'jpg';
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = __DIR__ . '/../uploads/chat_avatars/' . $randomName;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $fields[] = 'avatar = ?';
        $params[] = 'uploads/chat_avatars/' . $randomName;
    }
}

if (empty($fields)) json_out(['ok' => false, 'error' => 'nothing_to_update'], 400);

$params[] = $chatId;
$pdo->prepare('UPDATE chats SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

json_out(['ok' => true]);
