<?php
/**
 * webhook.php — receives Telegram Update objects (set via setWebhook)
 * and stores new/updated users + incoming messages. Public endpoint,
 * no login required (Telegram itself calls this).
 */

declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Telegram.php';

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Only POST requests are accepted.'], 405);
}

$update = json_decode(file_get_contents('php://input'), true);
if (!is_array($update)) {
    respond(['ok' => true, 'note' => 'No valid JSON payload received.']);
}

$message = $update['message'] ?? null;
if (!$message || !isset($message['chat']['id'])) {
    respond(['ok' => true, 'note' => 'Update ignored (not a text message).']);
}

$chat      = $message['chat'];
$from      = $message['from'] ?? [];
$chatId    = (int) $chat['id'];
$firstName = trim((string) ($from['first_name'] ?? $chat['first_name'] ?? ''));
$lastName  = trim((string) ($from['last_name'] ?? $chat['last_name'] ?? ''));
$username  = trim((string) ($from['username'] ?? $chat['username'] ?? ''));
$text      = $message['text'] ?? ($message['caption'] ?? '[Unsupported message type]');

try {
    $pdo = Database::connection();

    $exists = $pdo->prepare('SELECT telegram_id FROM users WHERE telegram_id = :id');
    $exists->execute([':id' => $chatId]);

    if ($exists->fetch()) {
        $pdo->prepare('
            UPDATE users SET first_name = :fn, last_name = :ln, username = :un, last_active = CURRENT_TIMESTAMP
            WHERE telegram_id = :id
        ')->execute([':fn' => $firstName, ':ln' => $lastName, ':un' => $username, ':id' => $chatId]);
    } else {
        $pdo->prepare('
            INSERT INTO users (telegram_id, first_name, last_name, username, created_at, last_active)
            VALUES (:id, :fn, :ln, :un, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ')->execute([':id' => $chatId, ':fn' => $firstName, ':ln' => $lastName, ':un' => $username]);
    }

    $pdo->prepare("
        INSERT INTO messages (telegram_id, sender_type, message_text, status, is_read, created_at)
        VALUES (:id, 'user', :text, 'received', 0, CURRENT_TIMESTAMP)
    ")->execute([':id' => $chatId, ':text' => $text]);

    if (trim($text) === '/start') {
        Telegram::sendMessage($chatId, "👋 স্বাগতম, " . ($firstName ?: 'বন্ধু') . "! আপনার মেসেজ পেয়েছি, শীঘ্রই রিপ্লাই দেওয়া হবে।");
    }

    respond(['ok' => true]);
} catch (Throwable $e) {
    error_log('Webhook error: ' . $e->getMessage());
    respond(['ok' => false, 'error' => 'Internal error while processing update.']);
}
