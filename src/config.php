<?php
/**
 * config.php — central configuration loaded by every entry point.
 * Reads everything from environment variables (set on Render), with
 * sane local-dev fallbacks. No secrets are ever hard-coded here.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ---------------------------------------------------------------
// Paths
// ---------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));                 // project root
define('DB_DIR', APP_ROOT . '/database');              // OUTSIDE the public/ web root
define('DB_FILE', DB_DIR . '/bot.db');

if (!is_dir(DB_DIR)) {
    @mkdir(DB_DIR, 0777, true);
}

// ---------------------------------------------------------------
// Telegram
// ---------------------------------------------------------------
$botToken = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');
define('BOT_TOKEN', $botToken);

// ---------------------------------------------------------------
// Admin login credentials
// Defaults exist only so local dev doesn't break; ALWAYS override
// these with real values via Render environment variables.
// ---------------------------------------------------------------
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: ($_ENV['ADMIN_USERNAME'] ?? 'admin'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: ($_ENV['ADMIN_PASSWORD'] ?? 'change-me'));

// ---------------------------------------------------------------
// Session
// ---------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
