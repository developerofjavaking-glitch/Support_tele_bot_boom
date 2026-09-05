<?php
/**
 * api.php — authenticated AJAX API consumed by index.php.
 *
 * Actions:
 *   GET  ?action=get_users
 *   GET  ?action=get_messages&telegram_id={id}   (also marks the conversation read)
 *   GET  ?action=bot_status
 *   POST ?action=send_message      {telegram_id, message_text}
 *   POST ?action=broadcast         {message_text}
 *   POST ?action=set_webhook       {webhook_url}
 *   POST ?action=delete_conversation {telegram_id}
 */

declare(strict_types=1);
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Telegram.php';

Auth::requireLoginJson();

function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo    = Database::connection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'get_users':
        jsonResponse(['ok' => true, 'users' => Database::getUsersWithPreview()]);
        break;

    case 'get_messages':
        $telegramId = filter_input(INPUT_GET, 'telegram_id', FILTER_VALIDATE_INT);
        if (!$telegramId) {
            jsonResponse(['ok' => false, 'error' => 'A valid telegram_id is required.'], 400);
        }

        $stmt = $pdo->prepare('
            SELECT id, telegram_id, sender_type, message_text, status, created_at
            FROM messages WHERE telegram_id = :id ORDER BY created_at ASC, id ASC
        ');
        $stmt->execute([':id' => $telegramId]);
        $messages = $stmt->fetchAll();

        Database::markConversationRead($telegramId);

        jsonResponse(['ok' => true, 'messages' => $messages]);
        break;

    case 'bot_status':
        $me = Telegram::getMe();
        jsonResponse(['ok' => (bool) ($me['ok'] ?? false), 'bot' => $me['result'] ?? null]);
        break;

    case 'send_message':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $input       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $telegramId  = filter_var($input['telegram_id'] ?? null, FILTER_VALIDATE_INT);
        $messageText = trim((string) ($input['message_text'] ?? ''));

        if (!$telegramId || $messageText === '') {
            jsonResponse(['ok' => false, 'error' => 'telegram_id and message_text are required.'], 400);
        }

        $result = Telegram::sendMessage($telegramId, $messageText);
        $status = ($result['ok'] ?? false) ? 'sent' : 'failed';

        $pdo->prepare("
            INSERT INTO messages (telegram_id, sender_type, message_text, status, is_read, created_at)
            VALUES (:id, 'admin', :text, :status, 1, CURRENT_TIMESTAMP)
        ")->execute([':id' => $telegramId, ':text' => $messageText, ':status' => $status]);

        $pdo->prepare('UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE telegram_id = :id')
            ->execute([':id' => $telegramId]);

        jsonResponse(['ok' => $status === 'sent', 'status' => $status, 'telegram_response' => $result]);
        break;

    case 'broadcast':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $input       = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $messageText = trim((string) ($input['message_text'] ?? ''));

        if ($messageText === '') {
            jsonResponse(['ok' => false, 'error' => 'message_text is required.'], 400);
        }

        $userIds = $pdo->query('SELECT telegram_id FROM users')->fetchAll(PDO::FETCH_COLUMN);
        $insertStmt = $pdo->prepare("
            INSERT INTO messages (telegram_id, sender_type, message_text, status, is_read, created_at)
            VALUES (:id, 'admin', :text, :status, 1, CURRENT_TIMESTAMP)
        ");

        $sent = 0;
        $failed = 0;
        foreach ($userIds as $uid) {
            $result = Telegram::sendMessage((int) $uid, $messageText);
            $status = ($result['ok'] ?? false) ? 'sent' : 'failed';
            $status === 'sent' ? $sent++ : $failed++;
            $insertStmt->execute([':id' => $uid, ':text' => $messageText, ':status' => $status]);
            usleep(35000); // stay comfortably under Telegram's ~30 msg/sec limit
        }

        jsonResponse(['ok' => true, 'total' => count($userIds), 'sent' => $sent, 'failed' => $failed]);
        break;

    case 'set_webhook':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $input      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $webhookUrl = trim((string) ($input['webhook_url'] ?? ''));

        if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL) || stripos($webhookUrl, 'https://') !== 0) {
            jsonResponse(['ok' => false, 'error' => 'A valid HTTPS webhook_url is required.'], 400);
        }

        $result = Telegram::setWebhook($webhookUrl);
        jsonResponse(['ok' => (bool) ($result['ok'] ?? false), 'telegram_response' => $result]);
        break;

    case 'delete_conversation':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
        }

        $input      = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $telegramId = filter_var($input['telegram_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$telegramId) {
            jsonResponse(['ok' => false, 'error' => 'A valid telegram_id is required.'], 400);
        }

        Database::deleteConversation($telegramId);
        jsonResponse(['ok' => true]);
        break;

    default:
        jsonResponse(['ok' => false, 'error' => 'Unknown action.'], 404);
}
