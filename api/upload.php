<?php
require_once __DIR__ . '/_bootstrap.php';
csrf_check();

$chatId = (int)($_POST['chat_id'] ?? 0);
$replyTo = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
$forceType = $_POST['force_type'] ?? ''; // 'voice' from recorder

if ($chatId <= 0 || empty($_FILES['file'])) json_out(['ok' => false, 'error' => 'invalid_input'], 400);

$member = require_membership($pdo, $chatId, $uid);
$chatStmt = $pdo->prepare('SELECT * FROM chats WHERE id = ?');
$chatStmt->execute([$chatId]);
$chat = $chatStmt->fetch();
if ($chat['type'] === 'channel' && !in_array($member['role'], ['owner', 'admin'])) {
    json_out(['ok' => false, 'error' => 'channel_readonly'], 403);
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'error' => 'upload_error'], 400);
if ($file['size'] > MAX_UPLOAD_SIZE) json_out(['ok' => false, 'error' => 'too_large'], 400);

$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$mime = mime_content_type($file['tmp_name']) ?: '';

$subdir = 'files';
$type = 'file';

if ($forceType === 'voice') {
    $type = 'voice';
    $subdir = 'voice';
    if (!$ext) $ext = 'webm';
} elseif (in_array($ext, ['jpg','jpeg','png','webp','bmp']) || strpos($mime, 'image/') === 0) {
    $type = 'image';
    $subdir = 'files';
} elseif ($ext === 'gif' || $mime === 'image/gif') {
    $type = 'gif';
    $subdir = 'files';
} elseif (in_array($ext, ['ogg','oga','mp3','wav','webm','m4a']) && strpos($mime, 'audio/') === 0) {
    $type = 'voice';
    $subdir = 'voice';
}

$safeExt = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
$randomName = bin2hex(random_bytes(16)) . '.' . $safeExt;
$destDir = __DIR__ . '/../../uploads/' . $subdir . '/';
$destPath = $destDir . $randomName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_out(['ok' => false, 'error' => 'save_failed'], 500);
}

$relPath = 'uploads/' . $subdir . '/' . $randomName;

$stmt = $pdo->prepare('INSERT INTO messages (chat_id, sender_id, reply_to, type, file_path, file_name, created_at) VALUES (?,?,?,?,?,?,NOW())');
$stmt->execute([$chatId, $uid, $replyTo, $type, $relPath, safe_filename($origName)]);
$msgId = (int)$pdo->lastInsertId();

$upd = $pdo->prepare('UPDATE chat_members SET last_read_message_id = ? WHERE chat_id = ? AND user_id = ?');
$upd->execute([$msgId, $chatId, $uid]);

$pdo->prepare('UPDATE chat_members SET is_hidden = 0 WHERE chat_id = ? AND user_id != ?')->execute([$chatId, $uid]);

json_out(['ok' => true, 'message_id' => $msgId, 'file_path' => $relPath, 'type' => $type]);
