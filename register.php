<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user_id()) {
    header('Location: app.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{4,32}$/', $username)) {
        $error = 'نام کاربری باید ۴ تا ۳۲ کاراکتر و فقط شامل حروف انگلیسی، عدد و _ باشد.';
    } elseif ($displayName === '' || mb_strlen($displayName) > 64) {
        $error = 'نام نمایشی را وارد کنید.';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    } elseif ($password !== $password2) {
        $error = 'تکرار رمز عبور مطابقت ندارد.';
    } else {
        $existing = get_user_by_username($pdo, $username);
        if ($existing) {
            $error = 'این نام کاربری قبلا گرفته شده است.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, reg_ip, last_ip, os, created_at, last_seen) VALUES (?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute([$username, $hash, $displayName, client_ip(), client_ip(), detect_os($_SERVER['HTTP_USER_AGENT'] ?? '')]);
            $uid = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            if (!empty($_SESSION['pending_join_code'])) {
                $code = $_SESSION['pending_join_code'];
                unset($_SESSION['pending_join_code']);
                header('Location: join.php?code=' . urlencode($code));
                exit;
            }
            header('Location: app.php');
            exit;
        }
    }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>ثبت‌نام | ZxRelay</title>
<link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-logo">
      <svg width="64" height="64" viewBox="0 0 64 64"><defs><linearGradient id="g2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#22c55e"/></linearGradient></defs><circle cx="32" cy="32" r="30" fill="url(#g2)"/><path d="M46 20L18 32.5l9 3 3 9 4.5-7L42 44l4-24z" fill="#fff"/></svg>
    </div>
    <h1>ساخت حساب ZxRelay</h1>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <label>نام نمایشی</label>
      <input type="text" name="display_name" required maxlength="64" value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
      <label>نام کاربری (انگلیسی)</label>
      <input type="text" name="username" required pattern="[a-zA-Z0-9_]{4,32}" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      <label>رمز عبور</label>
      <input type="password" name="password" required minlength="6">
      <label>تکرار رمز عبور</label>
      <input type="password" name="password2" required minlength="6">
      <button type="submit" class="btn-primary">ثبت‌نام</button>
    </form>
    <div class="auth-switch">قبلا ثبت‌نام کرده‌اید؟ <a href="index.php">ورود</a></div>
  </div>
</body>
</html>
