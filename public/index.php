<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::requireLogin();
$adminName = htmlspecialchars($_SESSION['admin_username'] ?? 'admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Telegraph Web Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="text-slate-200">

<div id="toast-container"></div>

<div class="flex flex-col h-screen overflow-hidden">

  <!-- ============ TOP HEADER BAR ============ -->
  <header class="flex items-center justify-between px-4 py-3 bg-[#0f172a] border-b border-slate-800 shrink-0">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#229ed9] to-[#06b6d4] flex items-center justify-center">
        <i class="fa-brands fa-telegram text-white text-lg"></i>
      </div>
      <div>
        <h1 class="font-semibold text-white leading-tight">Telegraph Web Admin</h1>
        <div class="flex items-center gap-1.5 text-xs text-slate-400">
          <span id="statusDot" class="status-dot status-offline"></span>
          <span id="statusText">Checking bot...</span>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button onclick="openWebhookModal()" class="hidden sm:flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm transition">
        <i class="fa-solid fa-link"></i> Setup Webhook
      </button>
      <button onclick="openBroadcastModal()" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#229ed9] hover:bg-[#1c87bd] text-sm font-medium transition">
        <i class="fa-solid fa-bullhorn"></i> <span class="hidden sm:inline">Mass Broadcast</span>
      </button>
      <button onclick="openWebhookModal()" class="sm:hidden px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm"><i class="fa-solid fa-link"></i></button>

      <div class="relative ml-1">
        <button onclick="el('accountMenu').classList.toggle('hidden')" class="w-9 h-9 rounded-full bg-slate-800 hover:bg-slate-700 flex items-center justify-center text-sm">
          <i class="fa-solid fa-user"></i>
        </button>
        <div id="accountMenu" class="hidden absolute right-0 mt-2 w-44 bg-[#1e293b] border border-slate-700 rounded-lg shadow-xl overflow-hidden z-30">
          <div class="px-3 py-2 text-xs text-slate-400 border-b border-slate-700">Signed in as <span class="text-slate-200"><?= $adminName ?></span></div>
          <a href="logout.php" class="block px-3 py-2 text-sm hover:bg-slate-800 text-red-300"><i class="fa-solid fa-right-from-bracket mr-2"></i>Log Out</a>
        </div>
      </div>
    </div>
  </header>

  <!-- ============ MAIN SPLIT VIEW ============ -->
  <div class="flex flex-1 overflow-hidden relative">

    <!-- LEFT: USER LIST -->
    <aside id="sidebarPanel" class="sidebar-panel w-full md:w-[35%] lg:w-[30%] bg-[#0f172a] border-r border-slate-800 flex flex-col shrink-0">
      <div class="p-3 border-b border-slate-800">
        <div class="relative">
          <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
          <input id="searchInput" type="text" placeholder="Search name or @username"
                 class="w-full bg-slate-800 text-sm rounded-full pl-9 pr-3 py-2 outline-none focus:ring-2 focus:ring-[#06b6d4] placeholder-slate-500">
        </div>
      </div>
      <div id="userList" class="flex-1 overflow-y-auto"></div>
      <div id="userListEmpty" class="hidden flex-1 flex flex-col items-center justify-center text-slate-500 text-sm gap-2 p-6 text-center">
        <i class="fa-regular fa-comments text-3xl"></i>
        <span>No users yet. Once someone messages your bot, they'll show up here.</span>
      </div>
    </aside>

    <!-- RIGHT: CHAT AREA -->
    <main id="chatPanel" class="chat-panel flex-1 flex flex-col bg-[#1e293b] hidden md:flex">

      <div id="noChatSelected" class="flex-1 flex flex-col items-center justify-center text-slate-500 gap-3">
        <i class="fa-regular fa-paper-plane text-4xl"></i>
        <p>Select a conversation to start chatting</p>
      </div>

      <div id="activeChat" class="hidden flex-1 flex flex-col min-h-0">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-800 bg-[#182234] shrink-0">
          <button id="mobileBackBtn2" class="md:hidden text-slate-300"><i class="fa-solid fa-arrow-left"></i></button>
          <div id="chatAvatar" class="avatar w-10 h-10 rounded-full text-sm"></div>
          <div class="flex-1 min-w-0">
            <div id="chatName" class="font-medium text-white truncate"></div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
              <span id="chatTgId"></span>
              <a id="chatUsernameLink" href="#" target="_blank" class="text-[#06b6d4] hover:underline hidden"></a>
            </div>
          </div>
          <button onclick="deleteCurrentConversation()" title="Delete conversation"
                  class="w-8 h-8 rounded-full hover:bg-red-500/15 text-red-400 flex items-center justify-center transition">
            <i class="fa-solid fa-trash text-sm"></i>
          </button>
        </div>

        <div id="messagesArea" class="flex-1 overflow-y-auto px-4 py-4 space-y-3"></div>

        <div class="p-3 border-t border-slate-800 bg-[#182234] shrink-0">
          <div class="flex items-end gap-2 bg-slate-800 rounded-2xl px-3 py-2">
            <textarea id="messageInput" rows="1" placeholder="Type a message..."
                      class="flex-1 bg-transparent outline-none text-sm resize-none py-1 placeholder-slate-500"></textarea>
            <button id="sendBtn" class="w-9 h-9 rounded-full bg-[#229ed9] hover:bg-[#1c87bd] flex items-center justify-center shrink-0 transition disabled:opacity-50">
              <i class="fa-solid fa-paper-plane text-sm text-white"></i>
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- ============ BROADCAST MODAL ============ -->
<div id="broadcastModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="bg-[#1e293b] w-full max-w-md rounded-2xl p-5 border border-slate-700">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold text-lg flex items-center gap-2"><i class="fa-solid fa-bullhorn text-[#229ed9]"></i> Mass Broadcast</h2>
      <button onclick="closeModal('broadcastModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <p class="text-sm text-slate-400 mb-3">This message will be sent to every user saved in your database.</p>
    <textarea id="broadcastText" rows="4" placeholder="Write your broadcast message..."
              class="w-full bg-slate-800 rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-[#06b6d4] placeholder-slate-500"></textarea>
    <button id="broadcastSendBtn" onclick="sendBroadcast()"
            class="mt-4 w-full py-2.5 rounded-lg bg-[#229ed9] hover:bg-[#1c87bd] font-medium transition">
      Send to All Users
    </button>
    <div id="broadcastResult" class="mt-3 text-sm"></div>
  </div>
</div>

<!-- ============ WEBHOOK MODAL ============ -->
<div id="webhookModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="bg-[#1e293b] w-full max-w-md rounded-2xl p-5 border border-slate-700">
    <div class="flex items-center justify-between mb-4">
      <h2 class="font-semibold text-lg flex items-center gap-2"><i class="fa-solid fa-link text-[#229ed9]"></i> Setup Webhook</h2>
      <button onclick="closeModal('webhookModal')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <p class="text-sm text-slate-400 mb-3">
      Enter the public HTTPS URL of your deployed app followed by <code class="text-[#06b6d4]">/webhook.php</code>.
    </p>
    <input id="webhookUrlInput" type="text" placeholder="https://your-app.onrender.com/webhook.php"
           class="w-full bg-slate-800 rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-[#06b6d4] placeholder-slate-500">
    <button onclick="setWebhook()" class="mt-4 w-full py-2.5 rounded-lg bg-[#229ed9] hover:bg-[#1c87bd] font-medium transition">
      Save Webhook
    </button>
    <div id="webhookResult" class="mt-3 text-sm"></div>
  </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
