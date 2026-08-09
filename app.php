<?php
require_once __DIR__ . '/includes/functions.php';
$uid = require_login_page();
$me = get_user($pdo, $uid);
touch_last_seen($pdo, $uid);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>ZxRelay</title>
<link rel="stylesheet" href="./assets/css/style.css">
</head>
<body>
<div id="app" class="app-container" data-me-id="<?= (int)$me['id'] ?>" data-me-name="<?= htmlspecialchars($me['display_name']) ?>" data-csrf="<?= $csrf ?>" data-is-owner="<?= (int)$me['is_owner'] ?>" data-open-chat="<?= (int)($_GET['open'] ?? 0) ?>">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <button class="icon-btn" id="btnMenu" title="منو">
        <svg viewBox="0 0 24 24" width="24" height="24"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
      </button>
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="جستجو در چت‌ها و کاربران...">
      </div>
      <button class="icon-btn" id="btnNewChat" title="گفتگوی جدید">
        <svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>
    <div class="chat-list" id="chatList"></div>
  </aside>

  <!-- ===== MAIN CHAT AREA ===== -->
  <main class="main-panel" id="mainPanel">
    <div class="empty-state" id="emptyState">
      <svg width="120" height="120" viewBox="0 0 64 64" opacity="0.5"><defs><linearGradient id="ge" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8b5cf6"/><stop offset="1" stop-color="#22c55e"/></linearGradient></defs><circle cx="32" cy="32" r="30" fill="url(#ge)"/><path d="M46 20L18 32.5l9 3 3 9 4.5-7L42 44l4-24z" fill="#fff"/></svg>
      <p>یک گفتگو را برای شروع انتخاب کنید</p>
    </div>

    <div class="chat-window" id="chatWindow" style="display:none;">
      <div class="chat-header">
        <button class="icon-btn only-mobile" id="btnBack">
          <svg viewBox="0 0 24 24" width="24" height="24"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="chat-header-info" id="chatHeaderInfo">
          <span id="chatAvatarWrap"><img class="avatar" id="chatAvatar" src="" alt=""></span>
          <div>
            <div class="chat-title" id="chatTitle"></div>
            <div class="chat-subtitle" id="chatSubtitle"></div>
          </div>
        </div>
        <button class="icon-btn" id="btnChatMenu">
          <svg viewBox="0 0 24 24" width="22" height="22"><circle cx="12" cy="5" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="19" r="1.8" fill="currentColor"/></svg>
        </button>
        <div class="dropdown-menu" id="chatMenuDropdown"></div>
      </div>

      <div class="messages-scroll" id="messagesScroll">
        <div class="messages-list" id="messagesList"></div>
      </div>

      <div class="typing-bar" id="typingBar" style="display:none;"></div>

      <div class="reply-preview" id="replyPreview" style="display:none;">
        <div class="reply-preview-content" id="replyPreviewContent"></div>
        <button id="btnCancelReply">&times;</button>
      </div>

      <div class="input-area" id="inputArea">
        <button class="icon-btn" id="btnAttach">
          <svg viewBox="0 0 24 24" width="22" height="22"><path d="M21 12.5V7a4 4 0 00-8 0v10a2.5 2.5 0 005 0V9" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
        </button>
        <input type="file" id="fileInput" style="display:none;">
        <button class="icon-btn" id="btnSticker">
          <svg viewBox="0 0 24 24" width="22" height="22"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/><circle cx="9" cy="10" r="1.2" fill="currentColor"/><circle cx="15" cy="10" r="1.2" fill="currentColor"/><path d="M8 15c1.2 1.3 2.6 2 4 2s2.8-.7 4-2" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
        </button>
        <textarea id="messageInput" rows="1" placeholder="پیامی بنویسید..."></textarea>
        <button class="icon-btn" id="btnVoice">
          <svg viewBox="0 0 24 24" width="22" height="22"><rect x="9" y="3" width="6" height="12" rx="3" stroke="currentColor" stroke-width="2" fill="none"/><path d="M5 11a7 7 0 0014 0M12 18v3" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
        </button>
        <button class="icon-btn send-btn" id="btnSend">
          <svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 12l16-7-6 16-3-7-7-2z" fill="currentColor"/></svg>
        </button>
      </div>
      <div class="channel-notice" id="channelNotice" style="display:none;">فقط ادمین‌های کانال می‌توانند پیام ارسال کنند</div>
    </div>
  </main>
</div>

<!-- ===== SIDE MENU ===== -->
<div class="overlay" id="overlaySideMenu"></div>
<div class="side-menu" id="sideMenu">
  <div class="side-menu-profile" id="sideMenuProfile"></div>
  <div class="side-menu-item" data-action="contacts">👤 مخاطبین</div>
  <div class="side-menu-item" data-action="new-group">👥 ساخت گروه جدید</div>
  <div class="side-menu-item" data-action="new-channel">📢 ساخت کانال جدید</div>
  <div class="side-menu-item" data-action="settings">⚙️ تنظیمات</div>
  <div class="side-menu-item" data-action="admin" id="menuAdminItem" style="display:none;">🛠 پنل مدیریت</div>
  <div class="side-menu-item" data-action="logout">🚪 خروج از حساب</div>
</div>

<!-- ===== GENERIC MODAL ===== -->
<div class="overlay" id="overlayModal"></div>
<div class="modal" id="modal">
  <div class="modal-header">
    <span id="modalTitle"></span>
    <button id="modalClose">&times;</button>
  </div>
  <div class="modal-body" id="modalBody"></div>
</div>

<!-- toast -->
<div id="toast" class="toast"></div>

<script src="./assets/js/app.js"></script>
</body>
</html>
