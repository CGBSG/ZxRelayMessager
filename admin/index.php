<?php

$logFile = __DIR__ . '/requests.log';

$data = [
    'time'    => date('Y-m-d H:i:s'),
    'ip'      => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'url'     => ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' .
                 ($_SERVER['HTTP_HOST'] ?? '') .
                 ($_SERVER['REQUEST_URI'] ?? ''),
//    'headers' => function_exists('getallheaders') ? getallheaders() : [],
    'get'     => $_GET,
    'post'    => $_POST,
    'body'    => file_get_contents('php://input'),
];

file_put_contents(
    $logFile,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

require_once __DIR__ . '/../includes/functions.php';
$uid = require_login_page();
$me = get_user($pdo, $uid);
if (!$me['is_owner']) {
    header('Location: ./app.php');
    exit;
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت | ZxRelay</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body" data-csrf="<?= $csrf ?>">
<div class="admin-wrap">
  <div class="admin-topbar">
    <div class="admin-brand">🛠 پنل مدیریت ZxRelay</div>
    <a href="../app.php" class="btn-link">بازگشت به برنامه</a>
  </div>
  <div class="admin-tabs">
    <button class="admin-tab active" data-tab="users">کاربران</button>
    <button class="admin-tab" data-tab="chats">گروه‌ها و کانال‌ها</button>
    <button class="admin-tab" data-tab="broadcast">پیام همگانی</button>
  </div>

  <div class="admin-panel-section" id="tab-users">
    <div class="admin-table-wrap">
      <table class="admin-table" id="usersTable">
        <thead><tr>
          <th>نام</th><th>آیدی</th><th>پروفایل</th><th>IP</th><th>تاریخ ثبت‌نام</th><th>آخرین بازدید</th><th>بیوگرافی</th><th>سیستم عامل</th><th>وضعیت</th><th></th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="admin-panel-section" id="tab-chats" style="display:none;">
    <div class="admin-table-wrap">
      <table class="admin-table" id="chatsTable">
        <thead><tr><th>نوع</th><th>عنوان</th><th>اعضا</th><th>لینک دعوت</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="admin-panel-section" id="tab-broadcast" style="display:none;">
    <h3>ارسال پیام همگانی به همه کاربران</h3>
    <textarea id="broadcastText" rows="5" placeholder="متن پیام همگانی..."></textarea>
    <button class="btn-primary" id="btnBroadcastSend" style="margin-top:10px;">ارسال به همه کاربران</button>
    <div id="broadcastResult" style="margin-top:10px;color:#22c55e;"></div>
  </div>
</div>
<div id="toast" class="toast"></div>
<script src="../assets/js/admin.js"></script>
</body>
</html>
