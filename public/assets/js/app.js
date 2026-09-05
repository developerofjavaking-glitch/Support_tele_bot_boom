/* =====================================================================
   Telegraph Web Admin — front-end logic
   AJAX polling every 3s keeps the user list and open chat in sync
   without a full page reload.
===================================================================== */

let currentUserId   = null;
let currentUserData = null;
let allUsers        = [];
let lastMessageIds  = new Set();

const el = (id) => document.getElementById(id);

// -------------------- Utilities --------------------
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function initials(first, last) {
  const a = (first || '').trim()[0] || '';
  const b = (last || '').trim()[0] || '';
  return (a + b).toUpperCase() || '?';
}

function avatarColor(id) {
  const colors = ['#229ed9','#06b6d4','#8b5cf6','#f97316','#ec4899','#22c55e','#eab308','#ef4444'];
  return colors[Math.abs(id) % colors.length];
}

function formatTime(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr.replace(' ', 'T') + 'Z');
  const now = new Date();
  const isToday = d.toDateString() === now.toDateString();
  const yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
  const isYesterday = d.toDateString() === yesterday.toDateString();

  const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  if (isToday) return time;
  if (isYesterday) return 'Yesterday';
  return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
}

function formatDateHeader(dateStr) {
  const d = new Date(dateStr.replace(' ', 'T') + 'Z');
  const now = new Date();
  if (d.toDateString() === now.toDateString()) return 'Today';
  const yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
  if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
  return d.toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' });
}

function showToast(msg, type = 'success') {
  const container = el('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast ' + (type === 'success' ? 'bg-emerald-600' : 'bg-red-600');
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

function closeModal(id) { el(id).classList.add('hidden'); }
function openBroadcastModal() {
  el('broadcastText').value = '';
  el('broadcastResult').textContent = '';
  el('broadcastModal').classList.remove('hidden');
}
function openWebhookModal() {
  el('webhookResult').textContent = '';
  el('webhookModal').classList.remove('hidden');
}

// -------------------- Bot status --------------------
async function checkBotStatus() {
  try {
    const res = await fetch('api.php?action=bot_status');
    const data = await res.json();
    if (data.ok && data.bot) {
      el('statusDot').className = 'status-dot status-online';
      el('statusText').textContent = 'Bot Active (@' + (data.bot.username || 'unknown') + ')';
    } else {
      el('statusDot').className = 'status-dot status-offline';
      el('statusText').textContent = 'Bot Offline / Token Missing';
    }
  } catch (e) {
    el('statusDot').className = 'status-dot status-offline';
    el('statusText').textContent = 'Connection error';
  }
}

// -------------------- User list --------------------
async function loadUsers(silent = false) {
  try {
    const res = await fetch('api.php?action=get_users');
    if (res.status === 401) { window.location.href = 'login.php'; return; }
    const data = await res.json();
    if (!data.ok) return;
    allUsers = data.users;
    renderUserList(el('searchInput').value);
  } catch (e) {
    if (!silent) showToast('Failed to load users', 'error');
  }
}

function renderUserList(filter = '') {
  const list = el('userList');
  const f = filter.trim().toLowerCase();

  const filtered = allUsers.filter(u => {
    const name = ((u.first_name || '') + ' ' + (u.last_name || '')).toLowerCase();
    const uname = (u.username || '').toLowerCase();
    return !f || name.includes(f) || uname.includes(f);
  });

  el('userListEmpty').classList.toggle('hidden', allUsers.length > 0);
  list.innerHTML = '';

  filtered.forEach(u => {
    const fullName = `${u.first_name || ''} ${u.last_name || ''}`.trim() || 'Unknown User';
    const isActive = currentUserId === u.telegram_id;
    const preview = u.last_message
      ? (u.last_message_sender === 'admin' ? 'You: ' : '') + u.last_message
      : 'No messages yet';
    const unread = parseInt(u.unread_count || 0, 10);

    const card = document.createElement('div');
    card.className = 'user-card flex items-center gap-3 px-3 py-3 cursor-pointer border-b border-slate-800/60' + (isActive ? ' active' : '');
    card.onclick = () => selectUser(u.telegram_id);
    card.innerHTML = `
      <div class="avatar w-11 h-11 rounded-full text-sm" style="background:${avatarColor(u.telegram_id)}">${initials(u.first_name, u.last_name)}</div>
      <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2">
          <span class="font-medium text-sm text-white truncate">${escapeHtml(fullName)}${u.username ? ` <span class="text-slate-500 font-normal">@${escapeHtml(u.username)}</span>` : ''}</span>
          <span class="text-[11px] text-slate-500 shrink-0">${formatTime(u.last_message_at)}</span>
        </div>
        <div class="flex items-center justify-between gap-2 mt-0.5">
          <div class="text-xs text-slate-400 truncate">${escapeHtml(preview)}</div>
          ${unread > 0 ? `<span class="unread-badge shrink-0">${unread > 99 ? '99+' : unread}</span>` : ''}
        </div>
      </div>
    `;
    list.appendChild(card);
  });
}

el('searchInput').addEventListener('input', (e) => renderUserList(e.target.value));

// -------------------- Chat / messages --------------------
async function selectUser(telegramId) {
  currentUserId = telegramId;
  currentUserData = allUsers.find(u => u.telegram_id === telegramId);

  el('noChatSelected').classList.add('hidden');
  el('activeChat').classList.remove('hidden');
  el('chatPanel').classList.remove('hidden');
  if (window.innerWidth < 768) {
    el('sidebarPanel').classList.add('hidden');
  }

  const fullName = `${currentUserData?.first_name || ''} ${currentUserData?.last_name || ''}`.trim() || 'Unknown User';
  el('chatName').textContent = fullName;
  el('chatTgId').textContent = 'ID: ' + telegramId;
  el('chatAvatar').textContent = initials(currentUserData?.first_name, currentUserData?.last_name);
  el('chatAvatar').style.background = avatarColor(telegramId);

  const unameLink = el('chatUsernameLink');
  if (currentUserData?.username) {
    unameLink.href = 'https://t.me/' + currentUserData.username;
    unameLink.textContent = '@' + currentUserData.username;
    unameLink.classList.remove('hidden');
  } else {
    unameLink.classList.add('hidden');
  }

  lastMessageIds = new Set();
  el('messagesArea').innerHTML = '';
  await loadMessages(true);   // this also marks the conversation read server-side
  await loadUsers(true);      // refresh unread badges after marking read
}

async function loadMessages(scrollToBottom = false) {
  if (!currentUserId) return;
  try {
    const res = await fetch('api.php?action=get_messages&telegram_id=' + currentUserId);
    if (res.status === 401) { window.location.href = 'login.php'; return; }
    const data = await res.json();
    if (!data.ok) return;
    renderMessages(data.messages);
    if (scrollToBottom) {
      const area = el('messagesArea');
      area.scrollTop = area.scrollHeight;
    }
  } catch (e) { /* silent fail on polling */ }
}

function renderMessages(messages) {
  const area = el('messagesArea');
  const newIds = new Set(messages.map(m => m.id));

  if (newIds.size === lastMessageIds.size && [...newIds].every(id => lastMessageIds.has(id))) {
    return;
  }

  const wasAtBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 60;
  lastMessageIds = newIds;
  area.innerHTML = '';

  let currentDateLabel = '';
  messages.forEach(m => {
    const dateLabel = formatDateHeader(m.created_at);
    if (dateLabel !== currentDateLabel) {
      currentDateLabel = dateLabel;
      const divider = document.createElement('div');
      divider.className = 'flex justify-center my-2';
      divider.innerHTML = `<span class="text-[11px] bg-slate-800 text-slate-400 px-3 py-1 rounded-full">${dateLabel}</span>`;
      area.appendChild(divider);
    }

    const isAdmin = m.sender_type === 'admin';
    const row = document.createElement('div');
    row.className = 'flex ' + (isAdmin ? 'justify-end' : 'justify-start');

    let statusIcon = '';
    if (isAdmin) {
      statusIcon = m.status === 'sent'
        ? '<i class="fa-solid fa-check-double text-[11px] ml-1 opacity-80"></i>'
        : m.status === 'failed'
          ? '<i class="fa-solid fa-triangle-exclamation text-[11px] ml-1 text-yellow-300"></i>'
          : '<i class="fa-solid fa-check text-[11px] ml-1 opacity-80"></i>';
    }

    row.innerHTML = `
      <div class="max-w-[75%] px-3 py-2 text-sm text-white ${isAdmin ? 'bubble-out' : 'bubble-in'}">
        <div class="whitespace-pre-wrap break-words">${escapeHtml(m.message_text)}</div>
        <div class="flex items-center justify-end gap-1 mt-1 text-[10px] opacity-70">
          <span>${formatTime(m.created_at)}</span>
          ${statusIcon}
        </div>
      </div>
    `;
    area.appendChild(row);
  });

  if (wasAtBottom) area.scrollTop = area.scrollHeight;
}

// -------------------- Sending a message --------------------
async function sendMessage() {
  const input = el('messageInput');
  const text = input.value.trim();
  if (!text || !currentUserId) return;

  input.value = '';
  autoSize(input);
  el('sendBtn').disabled = true;

  try {
    const res = await fetch('api.php?action=send_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ telegram_id: currentUserId, message_text: text }),
    });
    const data = await res.json();
    if (!data.ok) showToast('Message failed to send', 'error');
    await loadMessages(true);
    await loadUsers(true);
  } catch (e) {
    showToast('Network error while sending', 'error');
  } finally {
    el('sendBtn').disabled = false;
  }
}

el('sendBtn').addEventListener('click', sendMessage);
el('messageInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});
function autoSize(ta) {
  ta.style.height = 'auto';
  ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}
el('messageInput').addEventListener('input', (e) => autoSize(e.target));

// -------------------- Delete conversation --------------------
async function deleteCurrentConversation() {
  if (!currentUserId) return;
  if (!confirm('Delete this entire conversation and remove the user? This cannot be undone.')) return;

  try {
    const res = await fetch('api.php?action=delete_conversation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ telegram_id: currentUserId }),
    });
    const data = await res.json();
    if (data.ok) {
      showToast('Conversation deleted');
      currentUserId = null;
      el('activeChat').classList.add('hidden');
      el('noChatSelected').classList.remove('hidden');
      el('sidebarPanel').classList.remove('hidden');
      await loadUsers(true);
    } else {
      showToast('Failed to delete conversation', 'error');
    }
  } catch (e) {
    showToast('Network error', 'error');
  }
}

// -------------------- Broadcast --------------------
async function sendBroadcast() {
  const text = el('broadcastText').value.trim();
  if (!text) return;

  const btn = el('broadcastSendBtn');
  btn.disabled = true;
  btn.textContent = 'Sending...';
  el('broadcastResult').textContent = '';

  try {
    const res = await fetch('api.php?action=broadcast', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message_text: text }),
    });
    const data = await res.json();
    if (data.ok) {
      el('broadcastResult').innerHTML = `<span class="text-emerald-400">✔ Sent to ${data.sent}/${data.total} users${data.failed ? ' (' + data.failed + ' failed)' : ''}.</span>`;
      showToast('Broadcast sent to ' + data.sent + ' users');
      loadUsers(true);
      if (currentUserId) loadMessages(true);
    } else {
      el('broadcastResult').innerHTML = `<span class="text-red-400">✖ ${escapeHtml(data.error || 'Broadcast failed')}</span>`;
    }
  } catch (e) {
    el('broadcastResult').innerHTML = `<span class="text-red-400">✖ Network error</span>`;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Send to All Users';
  }
}

// -------------------- Webhook setup --------------------
async function setWebhook() {
  const url = el('webhookUrlInput').value.trim();
  if (!url) return;
  el('webhookResult').textContent = 'Saving...';

  try {
    const res = await fetch('api.php?action=set_webhook', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ webhook_url: url }),
    });
    const data = await res.json();
    if (data.ok) {
      el('webhookResult').innerHTML = '<span class="text-emerald-400">✔ Webhook set successfully.</span>';
      showToast('Webhook configured');
    } else {
      const errMsg = data.telegram_response?.description || data.error || 'Failed to set webhook';
      el('webhookResult').innerHTML = `<span class="text-red-400">✖ ${escapeHtml(errMsg)}</span>`;
    }
  } catch (e) {
    el('webhookResult').innerHTML = '<span class="text-red-400">✖ Network error</span>';
  }
}

// -------------------- Mobile back navigation --------------------
function backToList() {
  el('sidebarPanel').classList.remove('hidden');
  el('chatPanel').classList.add('hidden');
}
el('mobileBackBtn2')?.addEventListener('click', backToList);

// -------------------- Polling --------------------
checkBotStatus();
loadUsers();
setInterval(() => {
  loadUsers(true);
  if (currentUserId) loadMessages(false);
}, 3000);
setInterval(checkBotStatus, 30000);
