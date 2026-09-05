<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Auth.php';

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::attempt($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'ভুল ইউজারনেম বা পাসওয়ার্ড।';
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — Telegraph Web Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[#0f172a] text-slate-200 flex items-center justify-center min-h-screen">
  <div class="w-full max-w-sm bg-[#1e293b] border border-slate-800 rounded-2xl p-6 shadow-xl">
    <div class="flex flex-col items-center mb-6">
      <div class="w-12 h-12 rounded-full bg-gradient-to-br from-[#229ed9] to-[#06b6d4] flex items-center justify-center mb-3">
        <i class="fa-brands fa-telegram text-white text-2xl"></i>
      </div>
      <h1 class="text-lg font-semibold text-white">Telegraph Web Admin</h1>
      <p class="text-sm text-slate-400">Sign in to manage your bot</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 text-sm text-red-300 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-3">
      <div>
        <label class="text-xs text-slate-400">Username</label>
        <input type="text" name="username" required autofocus
               class="w-full bg-slate-800 rounded-lg px-3 py-2 mt-1 outline-none focus:ring-2 focus:ring-[#06b6d4] text-sm">
      </div>
      <div>
        <label class="text-xs text-slate-400">Password</label>
        <input type="password" name="password" required
               class="w-full bg-slate-800 rounded-lg px-3 py-2 mt-1 outline-none focus:ring-2 focus:ring-[#06b6d4] text-sm">
      </div>
      <button type="submit"
              class="w-full py-2.5 rounded-lg bg-[#229ed9] hover:bg-[#1c87bd] font-medium transition mt-2">
        Log In
      </button>
    </form>
  </div>
</body>
</html>
