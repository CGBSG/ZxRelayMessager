(function () {
'use strict';

const CSRF = document.body.dataset.csrf;
const toastEl = document.getElementById('toast');

function toast(msg) {
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  clearTimeout(toastEl._t);
  toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 2600);
}
function esc(s) {
  if (s === null || s === undefined) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function api(path, form) {
  const fd = form instanceof FormData ? form : new FormData();
  if (form && !(form instanceof FormData)) {
    Object.keys(form).forEach(k => fd.append(k, form[k]));
  }
  fd.append('csrf', CSRF);
  const res = await fetch(path, { method: form ? 'POST' : 'GET', body: form ? fd : undefined, credentials: 'same-origin' });
  try { return await res.json(); } catch (e) { return { ok: false }; }
}
async function apiGet(path) {
  const res = await fetch(path, { credentials: 'same-origin' });
  try { return await res.json(); } catch (e) { return { ok: false }; }
}

// ---------- tabs ----------
document.querySelectorAll('.admin-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    document.querySelectorAll('.admin-panel-section').forEach(s => s.style.display = 'none');
    document.getElementById('tab-' + tab.dataset.tab).style.display = 'block';
    if (tab.dataset.tab === 'users') loadUsers();
    if (tab.dataset.tab === 'chats') loadChats();
  });
});

// ---------- users tab ----------
async function loadUsers() {
  const data = await apiGet('api/users.php');
  if (!data.ok) return;
  const tbody = document.querySelector('#usersTable tbody');
  tbody.innerHTML = data.users.map(u => `
    <tr>
      <td>${esc(u.display_name)}${u.is_owner ? ' 👑' : ''}</td>
      <td>${u.id}</td>
      <td><a href="#" class="profile-link" data-uid="${u.id}">@${esc(u.username)}</a></td>
      <td>${esc(u.last_ip || u.reg_ip || '-')}</td>
      <td>${esc(u.created_at)}</td>
      <td>${esc(u.last_seen)}</td>
      <td style="max-width:180px;white-space:normal;">${esc(u.bio || '-')}</td>
      <td>${esc(u.os || '-')}</td>
      <td class="${u.is_banned ? 'badge-banned' : 'badge-ok'}">${u.is_banned ? 'مسدود' : 'فعال'}</td>
      <td>${u.is_owner ? '' : `<button class="small-btn ${u.is_banned ? '' : 'danger'}" data-ban="${u.id}" data-current="${u.is_banned}">${u.is_banned ? 'رفع مسدودیت' : 'مسدود کردن'}</button>`}</td>
    </tr>
  `).join('');

  tbody.querySelectorAll('[data-ban]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const banned = btn.dataset.current === '1' ? '' : '1';
      const res = await api('api/ban_user.php', { user_id: btn.dataset.ban, banned });
      if (res.ok) { toast('وضعیت کاربر به‌روزرسانی شد'); loadUsers(); }
    });
  });
  tbody.querySelectorAll('.profile-link').forEach(a => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      window.open('../app.php', '_blank');
    });
  });
}

// ---------- chats tab ----------
async function loadChats() {
  const data = await apiGet('api/chats.php');
  if (!data.ok) return;
  const tbody = document.querySelector('#chatsTable tbody');
  tbody.innerHTML = data.chats.map(c => `
    <tr>
      <td>${c.type === 'channel' ? '📢 کانال' : '👥 گروه'}</td>
      <td>${esc(c.title)}</td>
      <td>${c.member_count}</td>
      <td style="direction:ltr;text-align:left;">${esc(c.invite_code)}</td>
      <td><button class="small-btn" data-join="${c.id}">عضویت</button></td>
    </tr>
  `).join('');
  tbody.querySelectorAll('[data-join]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const res = await api('api/join_chat.php', { chat_id: btn.dataset.join });
      if (res.ok) { toast('عضو شدید'); window.open('../app.php', '_blank'); }
    });
  });
}

// ---------- broadcast tab ----------
document.getElementById('btnBroadcastSend').addEventListener('click', async () => {
  const text = document.getElementById('broadcastText').value.trim();
  if (!text) return toast('متن پیام را وارد کنید');
  if (!confirm('این پیام برای همه کاربران ارسال می‌شود. ادامه می‌دهید؟')) return;
  const res = await api('api/broadcast.php', { text });
  if (res.ok) {
    document.getElementById('broadcastResult').textContent = `پیام برای ${res.recipients} کاربر ارسال شد.`;
    document.getElementById('broadcastText').value = '';
    toast('پیام همگانی ارسال شد');
  } else {
    toast('ارسال ناموفق بود');
  }
});

loadUsers();
})();
