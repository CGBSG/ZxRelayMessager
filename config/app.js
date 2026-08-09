(function () {
'use strict';

const appEl = document.getElementById('app');
const ME_ID = parseInt(appEl.dataset.meId, 10);
const ME_NAME = appEl.dataset.meName;
const CSRF = appEl.dataset.csrf;
const IS_OWNER = appEl.dataset.isOwner === '1';
const OPEN_CHAT_ON_LOAD = parseInt(appEl.dataset.openChat, 10) || 0;

const chatListEl = document.getElementById('chatList');
const emptyStateEl = document.getElementById('emptyState');
const chatWindowEl = document.getElementById('chatWindow');
const messagesScrollEl = document.getElementById('messagesScroll');
const messagesListEl = document.getElementById('messagesList');
const chatTitleEl = document.getElementById('chatTitle');
const chatSubtitleEl = document.getElementById('chatSubtitle');
const chatAvatarEl = document.getElementById('chatAvatar');
const messageInputEl = document.getElementById('messageInput');
const typingBarEl = document.getElementById('typingBar');
const replyPreviewEl = document.getElementById('replyPreview');
const replyPreviewContentEl = document.getElementById('replyPreviewContent');
const channelNoticeEl = document.getElementById('channelNotice');
const inputAreaEl = document.getElementById('inputArea');
const toastEl = document.getElementById('toast');

let chats = [];
let chatsById = {};
let currentChatId = null;
let currentChatType = null;
let currentChatMaxMsgId = 0;
let replyToId = null;
let editingMessageId = null;
let myLastReadBeforeOpen = 0;
let unreadDividerShown = false;
let notifSinceId = 0;
let notifPermissionAsked = false;
let mediaRecorder = null;
let recordedChunks = [];
let isRecording = false;
let typingSendTimer = null;

// ---------- helpers ----------
function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function toast(msg) {
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  clearTimeout(toastEl._t);
  toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 2600);
}
function fmtTime(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ', 'T') + 'Z');
  return d.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' });
}
function fmtLastSeen(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ', 'T') + 'Z');
  const diffMin = Math.floor((Date.now() - d.getTime()) / 60000);
  if (diffMin < 2) return 'آنلاین';
  if (diffMin < 60) return diffMin + ' دقیقه پیش دیده شده';
  if (diffMin < 1440) return Math.floor(diffMin / 60) + ' ساعت پیش دیده شده';
  return Math.floor(diffMin / 1440) + ' روز پیش دیده شده';
}
function avatarHtml(url, name, size) {
  size = size || 50;
  if (url) {
    return `<img class="avatar" style="width:${size}px;height:${size}px" src="../${esc(url)}" alt="">`;
  }
  const letter = (name || '?').trim().charAt(0).toUpperCase();
  return `<div class="avatar-fallback" style="width:${size}px;height:${size}px;font-size:${Math.round(size*0.36)}px">${esc(letter)}</div>`;
}

async function api(path, { method = null, form = null, json = null } = {}) {
  if (!method) method = (form || json) ? 'POST' : 'GET';
  const opts = { method, credentials: 'same-origin', headers: {} };
  if (form) {
    if (!(form instanceof FormData)) {
      const fd = new FormData();
      Object.keys(form).forEach(k => { if (form[k] !== undefined && form[k] !== null) fd.append(k, form[k]); });
      form = fd;
    }
    form.append('csrf', CSRF);
    opts.body = form;
  } else if (json) {
    opts.headers['Content-Type'] = 'application/json';
    opts.headers['X-CSRF-Token'] = CSRF;
    opts.body = JSON.stringify(json);
  }
  let res, data;
  try {
    res = await fetch('api/' + path, opts);
    data = await res.json();
  } catch (e) {
    console.error('api() failed for', path, e);
    data = { ok: false, error: 'network_error' };
  }
  return data;
}

// ---------- side menu ----------
const sideMenu = document.getElementById('sideMenu');
const overlaySideMenu = document.getElementById('overlaySideMenu');
document.getElementById('btnMenu').addEventListener('click', () => {
  document.getElementById('sideMenuProfile').innerHTML =
    `<div class="name">${esc(ME_NAME)}</div><div class="username">ZxRelay</div>`;
  document.getElementById('menuAdminItem').style.display = IS_OWNER ? 'block' : 'none';
  sideMenu.classList.add('open');
  overlaySideMenu.classList.add('open');
});
overlaySideMenu.addEventListener('click', closeSideMenu);
function closeSideMenu() {
  sideMenu.classList.remove('open');
  overlaySideMenu.classList.remove('open');
}
sideMenu.addEventListener('click', (e) => {
  const item = e.target.closest('.side-menu-item');
  if (!item) return;
  const action = item.dataset.action;
  closeSideMenu();
  if (action === 'contacts') openContactsModal();
  else if (action === 'new-group') openNewGroupModal();
  else if (action === 'new-channel') openNewChannelModal();
  else if (action === 'settings') openSettingsModal();
  else if (action === 'admin') window.location.href = 'admin/index.php';
  else if (action === 'logout') window.location.href = 'logout.php';
});

// ---------- generic modal ----------
const modalEl = document.getElementById('modal');
const overlayModalEl = document.getElementById('overlayModal');
const modalTitleEl = document.getElementById('modalTitle');
const modalBodyEl = document.getElementById('modalBody');
function openModal(title, html) {
  modalTitleEl.textContent = title;
  modalBodyEl.innerHTML = html;
  modalEl.classList.add('open');
  overlayModalEl.classList.add('open');
}
function closeModal() {
  modalEl.classList.remove('open');
  overlayModalEl.classList.remove('open');
}
document.getElementById('modalClose').addEventListener('click', closeModal);
overlayModalEl.addEventListener('click', closeModal);

// ---------- chat list ----------
async function loadChats(preserveScroll = true) {
  const data = await api('get_chats.php');
  if (!data.ok) return;
  chats = data.chats;
  chatsById = {};
  chats.forEach(c => chatsById[c.id] = c);
  renderChatList();
}

function renderChatList() {
  const scrollTop = chatListEl.scrollTop;
  if (!chats.length) {
    chatListEl.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-dim);font-size:13px;">هنوز گفتگویی ندارید. با دکمه + شروع کنید.</div>';
    return;
  }
  chatListEl.innerHTML = chats.map(c => {
    const active = c.id === currentChatId ? 'active' : '';
    const muted = c.muted ? '<span class="muted-icon">🔕</span>' : '';
    const badge = c.unread_count > 0 ? `<div class="unread-badge">${c.unread_count > 99 ? '99+' : c.unread_count}</div>` : '';
    return `<div class="chat-item ${active}" data-chat-id="${c.id}">
      ${avatarHtml(c.avatar, c.title, 50)}
      <div class="chat-meta">
        <div class="chat-row1">
          <div class="chat-name">${esc(c.title || 'بدون‌نام')}</div>
          <div class="chat-time">${c.last_time ? fmtTime(c.last_time) : ''}</div>
        </div>
        <div class="chat-row2">
          <div class="chat-preview">${muted}${esc(c.last_message || 'گفتگوی جدید')}</div>
          ${badge}
        </div>
      </div>
    </div>`;
  }).join('');
  chatListEl.scrollTop = scrollTop;
}

chatListEl.addEventListener('click', (e) => {
  const item = e.target.closest('.chat-item[data-chat-id]');
  if (!item) return;
  openChat(parseInt(item.dataset.chatId, 10));
});

document.getElementById('searchInput').addEventListener('input', async (e) => {
  const q = e.target.value.trim();
  if (q.length < 2) { renderChatList(); return; }
  const data = await api('search_users.php?q=' + encodeURIComponent(q));
  const users = data.users || [];
  chatListEl.innerHTML = `<div style="padding:10px 14px;font-size:12px;color:var(--text-dim);">نتایج جستجوی کاربران</div>` +
    users.map(u => `<div class="chat-item" data-start-user="${u.id}">
        ${avatarHtml(u.avatar, u.display_name, 50)}
        <div class="chat-meta"><div class="chat-name">${esc(u.display_name)}</div><div class="chat-preview">@${esc(u.username)}</div></div>
      </div>`).join('') || '<div style="padding:20px;text-align:center;color:var(--text-dim);">کاربری پیدا نشد</div>';
});
chatListEl.addEventListener('click', async (e) => {
  const item = e.target.closest('[data-start-user]');
  if (!item) return;
  const uid = parseInt(item.dataset.startUser, 10);
  const data = await api('start_private_chat.php', { form: { user_id: uid } });
  if (data.ok) {
    document.getElementById('searchInput').value = '';
    await loadChats();
    openChat(data.chat_id);
  }
});

// ---------- new chat (+) button ----------
document.getElementById('btnNewChat').addEventListener('click', () => {
  openModal('گفتگوی جدید', `
    <label>لینک دعوت گروه/کانال را وارد کنید</label>
    <input type="text" id="joinCodeInput" placeholder="کد دعوت...">
    <div class="form-actions"><button class="btn-primary" id="btnJoinByCode">پیوستن</button></div>
    <hr style="border-color:var(--border);margin:16px 0;">
    <label>یا یک کاربر را جستجو کنید</label>
    <input type="text" id="userSearchInModal" placeholder="نام یا نام کاربری...">
    <div id="userSearchResults" style="margin-top:10px;"></div>
  `);
  document.getElementById('btnJoinByCode').addEventListener('click', async () => {
    const code = document.getElementById('joinCodeInput').value.trim();
    if (!code) return;
    const data = await api('join_by_code.php', { form: { code } });
    if (data.ok) { closeModal(); await loadChats(); openChat(data.chat_id); toast('عضو شدید'); }
    else toast('کد دعوت نامعتبر است');
  });
  document.getElementById('userSearchInModal').addEventListener('input', async (e) => {
    const q = e.target.value.trim();
    const box = document.getElementById('userSearchResults');
    if (q.length < 2) { box.innerHTML = ''; return; }
    const data = await api('search_users.php?q=' + encodeURIComponent(q));
    box.innerHTML = (data.users || []).map(u => `<div class="user-pick-item" data-uid="${u.id}">
        ${avatarHtml(u.avatar, u.display_name, 38)}
        <div>${esc(u.display_name)} <span style="color:var(--text-dim);font-size:12px;">@${esc(u.username)}</span></div>
      </div>`).join('') || '<div style="color:var(--text-dim);font-size:13px;">یافت نشد</div>';
    box.querySelectorAll('.user-pick-item').forEach(el => {
      el.addEventListener('click', async () => {
        const data2 = await api('start_private_chat.php', { form: { user_id: el.dataset.uid } });
        if (data2.ok) { closeModal(); await loadChats(); openChat(data2.chat_id); }
      });
    });
  });
});

// ---------- open a chat ----------
async function openChat(chatId) {
  currentChatId = chatId;
  const chat = chatsById[chatId];
  currentChatType = chat ? chat.type : null;
  replyToId = null;
  editingMessageId = null;
  hideReplyPreview();
  unreadDividerShown = false;

  emptyStateEl.style.display = 'none';
  chatWindowEl.style.display = 'flex';
  appEl.classList.add('show-chat');

  renderChatList();

  const bgClass = 'bg-' + (localStorage.getItem('zxr_bg') || 'default');
  messagesScrollEl.className = 'messages-scroll ' + (bgClass !== 'bg-default' ? bgClass : '');

  if (chat) {
    chatTitleEl.textContent = chat.title || 'بدون‌نام';
    document.getElementById('chatAvatarWrap').innerHTML = avatarHtml(chat.avatar, chat.title, 40);
    if (chat.type === 'private') {
      chatSubtitleEl.textContent = chat.other_last_seen ? fmtLastSeen(chat.other_last_seen) : '';
    } else {
      chatSubtitleEl.textContent = (chat.type === 'channel' ? 'کانال' : 'گروه') + ' · ' + chat.member_count + ' عضو';
    }
    channelNoticeEl.style.display = 'none';
    inputAreaEl.style.display = 'flex';
    if (chat.type === 'channel' && chat.role !== 'owner' && chat.role !== 'admin') {
      inputAreaEl.style.display = 'none';
      channelNoticeEl.style.display = 'block';
    }
    myLastReadBeforeOpen = 0; // will be set from get_messages response
  }

  messagesListEl.innerHTML = '<div style="text-align:center;color:var(--text-dim);padding:30px;">در حال بارگذاری...</div>';
  const data = await api('get_messages.php?chat_id=' + chatId);
  if (!data.ok) return;
  myLastReadBeforeOpen = data.my_last_read || 0;
  renderMessagesFull(data.messages);
  currentChatMaxMsgId = data.messages.length ? data.messages[data.messages.length - 1].id : 0;
  scrollToUnreadOrBottom();
  markRead(currentChatMaxMsgId);
}

document.getElementById('btnBack').addEventListener('click', backToList);
function backToList() {
  appEl.classList.remove('show-chat');
  currentChatId = null;
  renderChatList();
}

// swipe right-to-left gesture on mobile to go back (RTL app: swipe from right edge or general swipe)
(function initSwipe() {
  let startX = null, startY = null, startedAtEdge = false;
  chatWindowEl.addEventListener('touchstart', (e) => {
    const t = e.touches[0];
    startX = t.clientX; startY = t.clientY;
    startedAtEdge = t.clientX > window.innerWidth - 40; // near right edge (RTL back gesture)
  }, { passive: true });
  chatWindowEl.addEventListener('touchend', (e) => {
    if (startX === null) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - startX;
    const dy = Math.abs(t.clientY - startY);
    if (dx < -80 && dy < 60) { // fast swipe leftwards from right side = back in RTL
      backToList();
    }
    if (startedAtEdge && dx < -50 && dy < 80) backToList();
    startX = null;
  }, { passive: true });
})();

// ---------- rendering messages ----------
function renderMessagesFull(list) {
  messagesListEl.innerHTML = '';
  let dividerInserted = false;
  list.forEach(m => {
    if (!dividerInserted && myLastReadBeforeOpen && m.id > myLastReadBeforeOpen && m.sender_id !== ME_ID && m.type !== 'system') {
      const div = document.createElement('div');
      div.className = 'unread-divider';
      div.textContent = 'پیام‌های خوانده‌نشده';
      messagesListEl.appendChild(div);
      dividerInserted = true;
    }
    messagesListEl.appendChild(buildMessageNode(m));
  });
}
function appendMessages(list) {
  list.forEach(m => messagesListEl.appendChild(buildMessageNode(m)));
}

function buildMessageNode(m) {
  if (m.type === 'system') {
    const div = document.createElement('div');
    div.className = 'msg-system';
    div.textContent = m.content || '';
    return div;
  }
  const row = document.createElement('div');
  row.className = 'msg-row ' + (m.sender_id === ME_ID ? 'mine' : 'theirs');
  row.dataset.msgId = m.id;
  row.dataset.senderName = m.sender_id === ME_ID ? ME_NAME : (m.sender_name || 'کاربر');
  row.dataset.previewText = m.is_deleted ? 'پیام حذف شد' : (m.type === 'text' ? (m.content || '').slice(0, 80) : '[' + m.type + ']');

  let bodyHtml = '';
  if (m.is_deleted) {
    bodyHtml = '<i style="opacity:.6">پیام حذف شد</i>';
  } else if (m.type === 'text') {
    bodyHtml = esc(m.content);
  } else if (m.type === 'image') {
    bodyHtml = `<img class="msg-image" src="../${esc(m.file_path)}" onclick="window.open('../${esc(m.file_path)}','_blank')">`;
  } else if (m.type === 'gif') {
    bodyHtml = `<img class="msg-image" src="../${esc(m.file_path)}">`;
  } else if (m.type === 'sticker') {
    bodyHtml = `<img class="msg-sticker" src="../${esc(m.file_path)}">`;
  } else if (m.type === 'voice') {
    bodyHtml = `<div class="msg-voice"><audio controls src="../${esc(m.file_path)}"></audio></div>`;
  } else if (m.type === 'file') {
    bodyHtml = `<a class="msg-file" href="../${esc(m.file_path)}" download="${esc(m.file_name || 'file')}">📎 ${esc(m.file_name || 'دانلود فایل')}</a>`;
  }

  const replyHtml = m.reply_preview ? `<div class="msg-reply" data-goto="${m.reply_to}"><span class="msg-reply-sender">${esc(m.reply_preview.sender)}</span><br>${esc(m.reply_preview.text)}</div>` : '';
  const senderHtml = (currentChatType !== 'private' && m.sender_id !== ME_ID) ? `<div class="msg-sender">${esc(m.sender_name || 'کاربر')}</div>` : '';
  const editedHtml = m.edited && !m.is_deleted ? '<span class="msg-edited">ویرایش‌شده</span>' : '';

  const reactionsHtml = renderReactions(m);

  row.innerHTML = `
    <span class="swipe-reply-icon">↩️</span>
    <div>
      <div class="bubble">
        ${senderHtml}
        ${replyHtml}
        ${bodyHtml}
        <div class="msg-time">${editedHtml} ${fmtTime(m.created_at)}</div>
      </div>
      ${reactionsHtml}
      <div class="reaction-picker">
        ${['👍','👎','❤️','🔥','😂','😮','😢','🎉'].map(e => `<span data-emoji="${e}">${e}</span>`).join('')}
      </div>
    </div>
    <div class="msg-actions">
      <button data-act="react" title="ری‌اکشن">😊</button>
      <button data-act="reply" title="پاسخ">↩️</button>
      ${(!m.is_deleted && m.sender_id === ME_ID && m.type === 'text') ? '<button data-act="edit" title="ویرایش">✏️</button>' : ''}
      ${(!m.is_deleted) ? '<button data-act="delete" title="حذف">🗑️</button>' : ''}
    </div>
  `;
  attachSwipeReply(row);
  return row;
}

function attachSwipeReply(row) {
  const inner = row.querySelector(':scope > div');
  const icon = row.querySelector('.swipe-reply-icon');
  let startX = 0, startY = 0, dx = 0, active = false, horizontalDrag = false;

  row.addEventListener('touchstart', (e) => {
    if (e.touches.length !== 1) return;
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
    active = true; dx = 0; horizontalDrag = false;
  }, { passive: true });

  row.addEventListener('touchmove', (e) => {
    if (!active) return;
    const x = e.touches[0].clientX, y = e.touches[0].clientY;
    dx = x - startX;
    const dy = Math.abs(y - startY);
    if (Math.abs(dx) > 10 && Math.abs(dx) > dy) {
      horizontalDrag = true;
      e.stopPropagation();
      const clamped = Math.max(-70, Math.min(70, dx));
      inner.style.transition = 'none';
      inner.style.transform = `translateX(${clamped}px)`;
      icon.style.opacity = Math.min(1, Math.abs(clamped) / 45);
    }
  }, { passive: true });

  row.addEventListener('touchend', (e) => {
    if (!active) return;
    active = false;
    if (horizontalDrag) e.stopPropagation();
    inner.style.transition = 'transform .2s';
    inner.style.transform = 'translateX(0)';
    icon.style.opacity = 0;
    if (Math.abs(dx) > 45) {
      showReplyPreview(row.dataset.msgId, row.dataset.senderName, row.dataset.previewText);
      messageInputEl.focus();
    }
    dx = 0; horizontalDrag = false;
  });
}

function renderReactions(m) {
  if (!m.reactions || !m.reactions.length) return '';
  const counts = {};
  let mineEmoji = null;
  m.reactions.forEach(r => {
    counts[r.emoji] = (counts[r.emoji] || 0) + 1;
    if (r.user_id === ME_ID) mineEmoji = r.emoji;
  });
  return '<div class="reactions-row">' + Object.keys(counts).map(e =>
    `<div class="reaction-chip ${e === mineEmoji ? 'mine' : ''}" data-emoji="${e}">${e} ${counts[e]}</div>`
  ).join('') + '</div>';
}

messagesListEl.addEventListener('click', async (e) => {
  const gotoEl = e.target.closest('[data-goto]');
  if (gotoEl) {
    const target = messagesListEl.querySelector(`[data-msg-id="${gotoEl.dataset.goto}"]`);
    if (target) { target.scrollIntoView({ block: 'center', behavior: 'smooth' }); target.style.outline = '2px solid var(--green)'; setTimeout(() => target.style.outline = 'none', 1200); }
    return;
  }

  const chip = e.target.closest('.reaction-chip');
  if (chip) {
    const row = chip.closest('.msg-row');
    await api('react.php', { form: { message_id: row.dataset.msgId, emoji: chip.dataset.emoji } });
    refreshSingleMessage(row.dataset.msgId);
    return;
  }

  const pickerEmoji = e.target.closest('.reaction-picker span');
  if (pickerEmoji) {
    const row = e.target.closest('.msg-row');
    await api('react.php', { form: { message_id: row.dataset.msgId, emoji: pickerEmoji.dataset.emoji } });
    row.querySelector('.reaction-picker').classList.remove('open');
    refreshSingleMessage(row.dataset.msgId);
    return;
  }

  const actBtn = e.target.closest('[data-act]');
  if (actBtn) {
    const row = actBtn.closest('.msg-row');
    const msgId = row.dataset.msgId;
    const act = actBtn.dataset.act;
    if (act === 'react') {
      row.querySelector('.reaction-picker').classList.toggle('open');
    } else if (act === 'reply') {
      showReplyPreview(msgId, row.dataset.senderName, row.dataset.previewText);
      messageInputEl.focus();
    } else if (act === 'edit') {
      const textContent = row.querySelector('.bubble').childNodes;
      startEditMessage(msgId, row);
    } else if (act === 'delete') {
      if (confirm('این پیام حذف شود؟')) {
        await api('delete_message.php', { form: { message_id: msgId } });
        refreshSingleMessage(msgId);
      }
    }
  }
});

async function refreshSingleMessage(msgId) {
  // simplest reliable approach: refetch a small window and replace node
  const data = await api('get_messages.php?chat_id=' + currentChatId);
  if (!data.ok) return;
  const m = data.messages.find(x => String(x.id) === String(msgId));
  const row = messagesListEl.querySelector(`[data-msg-id="${msgId}"]`);
  if (m && row) {
    const newNode = buildMessageNode(m);
    row.replaceWith(newNode);
  }
}

function startEditMessage(msgId, row) {
  editingMessageId = msgId;
  const bubble = row.querySelector('.bubble');
  const currentText = bubble.childNodes[0] && bubble.childNodes[0].nodeType === 3 ? bubble.childNodes[0].textContent : '';
  messageInputEl.value = row.querySelector('.msg-reply') ? bubble.textContent.replace(row.querySelector('.msg-reply').textContent, '').trim() : bubble.textContent.trim();
  messageInputEl.focus();
  showReplyPreview(null, 'در حال ویرایش', 'پیام خود را ویرایش کنید و ارسال بزنید');
}

function showReplyPreview(msgId, senderName, text) {
  replyToId = msgId;
  replyPreviewEl.style.display = 'flex';
  replyPreviewContentEl.innerHTML = `<b>${esc(senderName)}</b><br>${esc(text || '')}`;
}
function hideReplyPreview() {
  replyToId = null;
  replyPreviewEl.style.display = 'none';
}
document.getElementById('btnCancelReply').addEventListener('click', () => {
  hideReplyPreview();
  if (editingMessageId) { editingMessageId = null; messageInputEl.value = ''; }
});

function scrollToUnreadOrBottom() {
  const divider = messagesListEl.querySelector('.unread-divider');
  if (divider) {
    divider.scrollIntoView({ block: 'center' });
  } else {
    messagesScrollEl.scrollTop = messagesScrollEl.scrollHeight;
  }
}

async function markRead(upToId) {
  if (!currentChatId || !upToId) return;
  await api('mark_read.php', { form: { chat_id: currentChatId, message_id: upToId } });
}

// ---------- sending messages ----------
async function sendCurrentMessage() {
  const text = messageInputEl.value.trim();
  if (!text || !currentChatId) return;

  if (editingMessageId) {
    await api('edit_message.php', { form: { message_id: editingMessageId, content: text } });
    editingMessageId = null;
    messageInputEl.value = '';
    hideReplyPreview();
    refreshMessagesTail();
    return;
  }

  messageInputEl.value = '';
  autoGrow();
  const data = await api('send_message.php', { form: { chat_id: currentChatId, content: text, reply_to: replyToId || '' } });
  hideReplyPreview();
  if (data.ok) {
    refreshMessagesTail();
    await loadChats();
  } else if (data.error === 'channel_readonly') {
    toast('فقط ادمین‌های کانال می‌توانند پیام ارسال کنند');
  }
}
document.getElementById('btnSend').addEventListener('click', sendCurrentMessage);
messageInputEl.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendCurrentMessage();
  }
});
function autoGrow() {
  messageInputEl.style.height = 'auto';
  messageInputEl.style.height = Math.min(messageInputEl.scrollHeight, 120) + 'px';
}
messageInputEl.addEventListener('input', () => {
  autoGrow();
  if (currentChatId) {
    clearTimeout(typingSendTimer);
    api('typing.php', { method: 'POST', form: { chat_id: currentChatId } });
  }
});

async function refreshMessagesTail() {
  if (!currentChatId) return;
  const data = await api('get_messages.php?chat_id=' + currentChatId + '&after_id=' + currentChatMaxMsgId);
  if (!data.ok || !data.messages.length) return;
  const wasAtBottom = messagesScrollEl.scrollHeight - messagesScrollEl.scrollTop - messagesScrollEl.clientHeight < 120;
  appendMessages(data.messages);
  currentChatMaxMsgId = data.messages[data.messages.length - 1].id;
  if (wasAtBottom) messagesScrollEl.scrollTop = messagesScrollEl.scrollHeight;
  markRead(currentChatMaxMsgId);
}

// ---------- file / image / voice / sticker upload ----------
document.getElementById('btnAttach').addEventListener('click', () => document.getElementById('fileInput').click());
document.getElementById('fileInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file || !currentChatId) return;
  const fd = new FormData();
  fd.append('chat_id', currentChatId);
  fd.append('file', file);
  if (replyToId) fd.append('reply_to', replyToId);
  toast('در حال آپلود...');
  const data = await api('upload.php', { form: fd });
  e.target.value = '';
  hideReplyPreview();
  if (data.ok) { refreshMessagesTail(); loadChats(); }
  else toast('آپلود ناموفق بود');
});

document.getElementById('btnSticker').addEventListener('click', () => {
  const stickers = Array.from({ length: 8 }, (_, i) => `sticker${i + 1}.svg`);
  openModal('استیکر / گیف', `
    <div class="sticker-grid">
      ${stickers.map(s => `<img src="../assets/stickers/${s}" data-sticker="${s}">`).join('')}
    </div>
    <hr style="border-color:var(--border);margin:16px 0;">
    <label>یا یک فایل گیف/عکس از دستگاه خود بفرستید</label>
    <input type="file" id="gifFileInput" accept="image/gif,image/*">
  `);
  modalBodyEl.querySelectorAll('[data-sticker]').forEach(img => {
    img.addEventListener('click', async () => {
      closeModal();
      const data = await api('send_sticker.php', { form: { chat_id: currentChatId, sticker: img.dataset.sticker } });
      if (data.ok) { refreshMessagesTail(); loadChats(); }
    });
  });
  document.getElementById('gifFileInput').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('chat_id', currentChatId);
    fd.append('file', file);
    closeModal();
    const data = await api('upload.php', { form: fd });
    if (data.ok) { refreshMessagesTail(); loadChats(); }
  });
});

const btnVoice = document.getElementById('btnVoice');
btnVoice.addEventListener('click', async () => {
  if (!currentChatId) return;
  if (!isRecording) {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      recordedChunks = [];
      mediaRecorder = new MediaRecorder(stream);
      mediaRecorder.ondataavailable = (e) => recordedChunks.push(e.data);
      mediaRecorder.onstop = async () => {
        const blob = new Blob(recordedChunks, { type: 'audio/webm' });
        stream.getTracks().forEach(t => t.stop());
        if (blob.size > 500) {
          const fd = new FormData();
          fd.append('chat_id', currentChatId);
          fd.append('force_type', 'voice');
          fd.append('file', blob, 'voice.webm');
          const data = await api('upload.php', { form: fd });
          if (data.ok) { refreshMessagesTail(); loadChats(); }
        }
      };
      mediaRecorder.start();
      isRecording = true;
      btnVoice.classList.add('recording');
      document.getElementById('btnSend').classList.add('recording');
    } catch (err) {
      toast('دسترسی به میکروفون داده نشد');
    }
  } else {
    mediaRecorder.stop();
    isRecording = false;
    btnVoice.classList.remove('recording');
    document.getElementById('btnSend').classList.remove('recording');
  }
});

// ---------- chat header menu ----------
const chatMenuDropdown = document.getElementById('chatMenuDropdown');
document.getElementById('btnChatMenu').addEventListener('click', () => {
  const chat = chatsById[currentChatId];
  if (!chat) return;
  let items = [];
  if (chat.type === 'private') {
    items.push({ act: 'view-profile', label: '👤 مشاهده پروفایل' });
  } else {
    items.push({ act: 'info', label: chat.type === 'channel' ? '📢 اطلاعات کانال' : '👥 اطلاعات گروه' });
  }
  items.push({ act: 'mute', label: chat.muted ? '🔔 فعال کردن اعلان' : '🔕 بی‌صدا کردن' });
  if (chat.type !== 'private') {
    items.push({ act: 'leave', label: (chat.type === 'channel' ? 'ترک کانال' : 'ترک گروه'), danger: true });
  }
  items.push({ act: 'delete', label: '🗑️ حذف گفتگو', danger: true });

  chatMenuDropdown.innerHTML = items.map(i => `<div class="dropdown-item ${i.danger ? 'danger' : ''}" data-act="${i.act}">${i.label}</div>`).join('');
  chatMenuDropdown.classList.toggle('open');
});
chatMenuDropdown.addEventListener('click', async (e) => {
  const item = e.target.closest('[data-act]');
  if (!item) return;
  chatMenuDropdown.classList.remove('open');
  const act = item.dataset.act;
  const chat = chatsById[currentChatId];
  if (act === 'info' || act === 'view-profile') openChatInfoModal();
  else if (act === 'mute') {
    await api('mute_chat.php', { form: { chat_id: currentChatId, muted: chat.muted ? '' : '1' } });
    await loadChats();
  } else if (act === 'leave') {
    if (confirm('از این گروه/کانال خارج شوید؟')) {
      await api('leave_chat.php', { form: { chat_id: currentChatId } });
      currentChatId = null;
      appEl.classList.remove('show-chat');
      emptyStateEl.style.display = 'flex';
      chatWindowEl.style.display = 'none';
      await loadChats();
    }
  } else if (act === 'delete') {
    if (confirm('این گفتگو از لیست شما حذف شود؟')) {
      await api('delete_chat.php', { form: { chat_id: currentChatId } });
      currentChatId = null;
      appEl.classList.remove('show-chat');
      emptyStateEl.style.display = 'flex';
      chatWindowEl.style.display = 'none';
      await loadChats();
    }
  }
});
document.addEventListener('click', (e) => {
  if (!e.target.closest('#btnChatMenu') && !e.target.closest('#chatMenuDropdown')) chatMenuDropdown.classList.remove('open');
});

async function openChatInfoModal() {
  const chat = chatsById[currentChatId];
  const infoData = await api('get_chat_info.php?chat_id=' + currentChatId);
  if (!infoData.ok) return;
  const info = infoData.chat;
  const isAdmin = info.my_role === 'owner' || info.my_role === 'admin';

  if (info.type === 'private') {
    openModal('پروفایل', `
      <div style="text-align:center;">
        ${avatarHtml(info.avatar, info.title, 90)}
        <h3 style="margin:10px 0 2px;">${esc(info.title)}</h3>
        <div style="color:var(--text-dim);font-size:13px;">${esc(info.bio || 'بدون بیوگرافی')}</div>
      </div>
    `);
    return;
  }

  const membersData = await api('get_members.php?chat_id=' + currentChatId);
  const members = membersData.members || [];

  let editSection = '';
  if (isAdmin) {
    editSection = `
      <label>نام</label><input type="text" id="chatInfoTitle" value="${esc(info.title)}">
      <label>بیوگرافی</label><textarea id="chatInfoBio">${esc(info.bio || '')}</textarea>
      <label>تصویر پروفایل</label><input type="file" id="chatInfoAvatar" accept="image/*">
      <div class="form-actions"><button class="btn-primary" id="btnSaveChatInfo">ذخیره تغییرات</button></div>
      <hr style="border-color:var(--border);margin:14px 0;">
    `;
  }

  openModal(info.type === 'channel' ? 'اطلاعات کانال' : 'اطلاعات گروه', `
    <div style="text-align:center;margin-bottom:10px;">
      ${avatarHtml(info.avatar, info.title, 80)}
      <h3 style="margin:8px 0 2px;">${esc(info.title)}</h3>
      <div style="color:var(--text-dim);font-size:13px;">${esc(info.bio || '')}</div>
      <div style="color:var(--text-dim);font-size:12px;margin-top:4px;">${info.member_count} عضو</div>
    </div>
    ${editSection}
    <label>لینک دعوت</label>
    <div class="invite-box"><span id="inviteLink">${location.origin + location.pathname.replace('app.php','join.php')}?code=${info.invite_code}</span>
      <button class="small-btn" id="btnCopyInvite">کپی</button></div>
    <label style="margin-top:16px;">اعضا</label>
    <div id="membersList">
      ${members.map(m => `<div class="member-item">
          ${avatarHtml(m.avatar, m.display_name, 32)}
          <div>${esc(m.display_name)}</div>
          <div class="role-badge">${m.role === 'owner' ? 'مالک' : m.role === 'admin' ? 'ادمین' : ''}</div>
          ${(isAdmin && m.role !== 'owner' && m.id != ME_ID) ? `<button class="kick-btn" data-kick="${m.id}">اخراج</button>` : ''}
        </div>`).join('')}
    </div>
  `);

  document.getElementById('btnCopyInvite')?.addEventListener('click', () => {
    navigator.clipboard.writeText(document.getElementById('inviteLink').textContent);
    toast('لینک کپی شد');
  });
  document.getElementById('btnSaveChatInfo')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('chat_id', currentChatId);
    fd.append('title', document.getElementById('chatInfoTitle').value.trim());
    fd.append('bio', document.getElementById('chatInfoBio').value.trim());
    const avatarFile = document.getElementById('chatInfoAvatar').files[0];
    if (avatarFile) fd.append('avatar', avatarFile);
    const res = await api('update_chat_info.php', { form: fd });
    if (res.ok) { toast('ذخیره شد'); closeModal(); await loadChats(); openChat(currentChatId); }
  });
  modalBodyEl.querySelectorAll('[data-kick]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('این کاربر اخراج شود؟')) return;
      await api('kick_member.php', { form: { chat_id: currentChatId, user_id: btn.dataset.kick } });
      openChatInfoModal();
    });
  });
}

// ---------- new group / channel ----------
async function openNewGroupModal() {
  const contactsData = await api('contacts.php');
  const contacts = contactsData.contacts || [];
  openModal('ساخت گروه جدید', `
    <label>نام گروه</label>
    <input type="text" id="groupTitle" placeholder="مثلا: دوستان دانشگاه">
    <label>اعضا را از مخاطبین انتخاب کنید</label>
    <div id="groupMembersPick">
      ${contacts.map(c => `<div class="user-pick-item" data-uid="${c.id}">
          ${avatarHtml(c.avatar, c.display_name, 34)}
          <div>${esc(c.display_name)}</div>
          <input type="checkbox" value="${c.id}">
        </div>`).join('') || '<div style="color:var(--text-dim);font-size:13px;">مخاطبی ندارید</div>'}
    </div>
    <div class="form-actions"><button class="btn-primary" id="btnCreateGroup">ساخت گروه</button></div>
  `);
  modalBodyEl.querySelectorAll('.user-pick-item').forEach(el => {
    el.addEventListener('click', (e) => {
      if (e.target.tagName !== 'INPUT') el.querySelector('input').checked = !el.querySelector('input').checked;
    });
  });
  document.getElementById('btnCreateGroup').addEventListener('click', async () => {
    const title = document.getElementById('groupTitle').value.trim();
    if (!title) return toast('نام گروه را وارد کنید');
    const ids = Array.from(modalBodyEl.querySelectorAll('#groupMembersPick input:checked')).map(i => parseInt(i.value, 10));
    const data = await api('create_group.php', { form: { title, member_ids: JSON.stringify(ids) } });
    if (data.ok) { closeModal(); await loadChats(); openChat(data.chat_id); toast('گروه ساخته شد'); }
  });
}

function openNewChannelModal() {
  openModal('ساخت کانال جدید', `
    <label>نام کانال</label>
    <input type="text" id="channelTitle" placeholder="مثلا: اخبار روزانه">
    <label>بیوگرافی کانال</label>
    <textarea id="channelBio" placeholder="توضیح کوتاه درباره کانال"></textarea>
    <div class="form-actions"><button class="btn-primary" id="btnCreateChannel">ساخت کانال</button></div>
  `);
  document.getElementById('btnCreateChannel').addEventListener('click', async () => {
    const title = document.getElementById('channelTitle').value.trim();
    const bio = document.getElementById('channelBio').value.trim();
    if (!title) return toast('نام کانال را وارد کنید');
    const data = await api('create_channel.php', { form: { title, bio } });
    if (data.ok) { closeModal(); await loadChats(); openChat(data.chat_id); toast('کانال ساخته شد'); }
  });
}

// ---------- contacts modal ----------
async function openContactsModal() {
  const data = await api('contacts.php');
  const contacts = data.contacts || [];
  openModal('مخاطبین', `
    <label>افزودن مخاطب جدید</label>
    <input type="text" id="addContactSearch" placeholder="جستجوی نام کاربری یا نام...">
    <div id="addContactResults"></div>
    <hr style="border-color:var(--border);margin:14px 0;">
    <label>مخاطبین شما</label>
    <div id="contactsListBox">
      ${contacts.map(c => `<div class="user-pick-item" data-start="${c.id}">
          ${avatarHtml(c.avatar, c.display_name, 38)}
          <div>${esc(c.display_name)}<br><span style="color:var(--text-dim);font-size:12px;">@${esc(c.username)}</span></div>
        </div>`).join('') || '<div style="color:var(--text-dim);font-size:13px;">مخاطبی ندارید</div>'}
    </div>
  `);
  modalBodyEl.querySelectorAll('[data-start]').forEach(el => {
    el.addEventListener('click', async () => {
      const res = await api('start_private_chat.php', { form: { user_id: el.dataset.start } });
      if (res.ok) { closeModal(); await loadChats(); openChat(res.chat_id); }
    });
  });
  document.getElementById('addContactSearch').addEventListener('input', async (e) => {
    const q = e.target.value.trim();
    const box = document.getElementById('addContactResults');
    if (q.length < 2) { box.innerHTML = ''; return; }
    const res = await api('search_users.php?q=' + encodeURIComponent(q));
    box.innerHTML = (res.users || []).map(u => `<div class="user-pick-item" data-add="${u.id}">
        ${avatarHtml(u.avatar, u.display_name, 34)}<div>${esc(u.display_name)} <span style="color:var(--text-dim);font-size:12px;">@${esc(u.username)}</span></div>
      </div>`).join('');
    box.querySelectorAll('[data-add]').forEach(el => {
      el.addEventListener('click', async () => {
        await api('add_contact.php', { form: { user_id: el.dataset.add } });
        toast('به مخاطبین اضافه شد');
        openContactsModal();
      });
    });
  });
}

// ---------- settings modal ----------
async function openSettingsModal() {
  const chatsData = chats; // not needed
  openModal('تنظیمات', `
    <div style="text-align:center;margin-bottom:10px;">
      <label>تصویر پروفایل</label>
      <input type="file" id="settingsAvatar" accept="image/*">
    </div>
    <label>نام نمایشی</label>
    <input type="text" id="settingsName" value="${esc(ME_NAME)}">
    <label>بیوگرافی</label>
    <textarea id="settingsBio" placeholder="درباره من..."></textarea>
    <label>نام کاربری</label>
    <input type="text" id="settingsUsername" placeholder="نام کاربری فعلی خود را برای تغییر وارد کنید">
    <label>رمز عبور فعلی (برای تغییر رمز یا یوزرنیم)</label>
    <input type="password" id="settingsCurrentPass">
    <label>رمز عبور جدید (اختیاری)</label>
    <input type="password" id="settingsNewPass">
    <div class="form-actions"><button class="btn-primary" id="btnSaveProfile">ذخیره</button></div>
    <hr style="border-color:var(--border);margin:16px 0;">
    <label>پس‌زمینه چت‌ها</label>
    <div class="bg-picker">
      ${['default','purple','green','dark','starry','waves'].map(b => `<div class="bg-swatch sw-${b} ${localStorage.getItem('zxr_bg') === b || (!localStorage.getItem('zxr_bg') && b === 'default') ? 'selected' : ''}" data-bg="${b}"></div>`).join('')}
    </div>
    <hr style="border-color:var(--border);margin:16px 0;">
    <label style="display:flex;align-items:center;gap:8px;">
      <input type="checkbox" id="settingsNotifMuted" style="width:auto;"> بی‌صدا کردن همه اعلان‌ها
    </label>
    <div class="form-actions"><button class="btn-secondary" id="btnRequestNotif">فعال‌سازی نوتیفیکیشن مرورگر</button></div>
  `);

  document.querySelectorAll('.bg-swatch').forEach(sw => {
    sw.addEventListener('click', async () => {
      document.querySelectorAll('.bg-swatch').forEach(s => s.classList.remove('selected'));
      sw.classList.add('selected');
      localStorage.setItem('zxr_bg', sw.dataset.bg);
      await api('update_profile.php', { form: { background: sw.dataset.bg } });
      if (currentChatId) {
        const bgClass = 'bg-' + sw.dataset.bg;
        messagesScrollEl.className = 'messages-scroll ' + (bgClass !== 'bg-default' ? bgClass : '');
      }
      toast('پس‌زمینه تغییر کرد');
    });
  });

  document.getElementById('btnRequestNotif').addEventListener('click', async () => {
    const perm = await Notification.requestPermission();
    toast(perm === 'granted' ? 'نوتیفیکیشن فعال شد' : 'دسترسی داده نشد');
  });

  document.getElementById('btnSaveProfile').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('display_name', document.getElementById('settingsName').value.trim());
    fd.append('bio', document.getElementById('settingsBio').value.trim());
    const username = document.getElementById('settingsUsername').value.trim();
    if (username) fd.append('username', username);
    const curPass = document.getElementById('settingsCurrentPass').value;
    const newPass = document.getElementById('settingsNewPass').value;
    if (newPass) { fd.append('new_password', newPass); fd.append('current_password', curPass); }
    fd.append('notif_muted', document.getElementById('settingsNotifMuted').checked ? '1' : '');
    const avatarFile = document.getElementById('settingsAvatar').files[0];
    if (avatarFile) fd.append('avatar', avatarFile);
    const res = await api('update_profile.php', { form: fd });
    if (res.ok) { toast('تغییرات ذخیره شد'); closeModal(); location.reload(); }
    else toast('خطا: ' + (res.error || 'نامشخص'));
  });
}

// ---------- typing indicator ----------
async function pollTyping() {
  if (!currentChatId) return;
  const data = await api('typing.php?chat_id=' + currentChatId);
  if (!data.ok) return;
  if (data.typing && data.typing.length) {
    typingBarEl.style.display = 'block';
    typingBarEl.textContent = data.typing.map(t => t.display_name).join('، ') + ' در حال نوشتن...';
  } else {
    typingBarEl.style.display = 'none';
  }
}

// ---------- notifications polling ----------
async function pollNotifications() {
  const data = await api('poll_notifications.php?since_id=' + notifSinceId);
  if (!data.ok) return;
  notifSinceId = data.max_id;
  (data.notifications || []).forEach(n => {
    if (n.chat_id === currentChatId && !document.hidden) return;
    if (Notification.permission === 'granted') {
      try {
        const notif = new Notification(n.title, { body: n.body });
        notif.onclick = () => { window.focus(); openChat(n.chat_id); };
      } catch (e) {}
    }
  });
}

// ---------- main polling loop ----------
setInterval(() => { loadChats(); }, 4000);
setInterval(() => { if (currentChatId) refreshMessagesTail(); }, 2500);
setInterval(() => { pollTyping(); }, 2000);
setInterval(() => { pollNotifications(); }, 5000);

// ---------- init ----------
(async function init() {
  await loadChats();
  if (OPEN_CHAT_ON_LOAD) openChat(OPEN_CHAT_ON_LOAD);
  if ('Notification' in window && Notification.permission === 'default') {
    setTimeout(() => Notification.requestPermission(), 1500);
  }
})();

})();
