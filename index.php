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
    $password = $_POST['password'] ?? '';

    $user = get_user_by_username($pdo, $username);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'نام کاربری یا رمز عبور اشتباه است.';
    } elseif ($user['is_banned']) {
        $error = 'حساب شما مسدود شده است.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $stmt = $pdo->prepare('UPDATE users SET last_seen = NOW(), last_ip = ?, os = ? WHERE id = ?');
        $stmt->execute([client_ip(), detect_os($_SERVER['HTTP_USER_AGENT'] ?? ''), $user['id']]);
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
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>ورود | ZxRelay</title>
<link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="auth-body">
  <div class="auth-card">
    <div class="auth-logo">
      <svg width="64" height="64" viewBox="0 0 64 64"><defs><linearGradient id="g1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#22c55e"/></linearGradient></defs><circle cx="32" cy="32" r="30" fill="url(#g1)"/><path d="M46 20L18 32.5l9 3 3 9 4.5-7L42 44l4-24z" fill="#fff"/></svg>
    </div>
    <h1>ZxRelay</h1>
    <p class="auth-sub">پیام‌رسان سریع، امن و شخصی</p>
    <?php if ($error): ?><div class="auth-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="auth-form">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <label>نام کاربری</label>
      <input type="text" name="username" required autofocus autocomplete="username">
      <label>رمز عبور</label>
      <input type="password" name="password" required autocomplete="current-password">
      <button type="submit" class="btn-primary">ورود</button>
    </form>
    <div class="auth-switch">حساب ندارید؟ <a href="register.php">ثبت‌نام</a></div>
  </div>
</body>
</html>
