<?php
/**
 * Database.php — thin wrapper around a single shared PDO/SQLite connection,
 * plus schema migration and small helper queries used across the app.
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO('sqlite:' . DB_FILE);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
                self::$pdo->exec('PRAGMA journal_mode = WAL;');
                self::migrate(self::$pdo);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                die(json_encode(['ok' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
            }
        }
        return self::$pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                telegram_id  INTEGER PRIMARY KEY,
                first_name   TEXT DEFAULT '',
                last_name    TEXT DEFAULT '',
                username     TEXT DEFAULT '',
                created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_active  DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS messages (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_id    INTEGER NOT NULL,
                sender_type    TEXT NOT NULL CHECK (sender_type IN ('user','admin')),
                message_text   TEXT NOT NULL,
                status         TEXT NOT NULL DEFAULT 'received' CHECK (status IN ('sent','received','failed')),
                is_read        INTEGER NOT NULL DEFAULT 0,
                created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (telegram_id) REFERENCES users(telegram_id) ON DELETE CASCADE
            );
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_telegram_id ON messages(telegram_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_messages_is_read ON messages(is_read);");
    }

    /** Users + last message preview + unread count, newest activity first. */
    public static function getUsersWithPreview(): array
    {
        $sql = "
            SELECT
                u.telegram_id,
                u.first_name,
                u.last_name,
                u.username,
                u.created_at,
                u.last_active,
                m.message_text AS last_message,
                m.created_at   AS last_message_at,
                m.sender_type  AS last_message_sender,
                (SELECT COUNT(*) FROM messages
                    WHERE telegram_id = u.telegram_id
                      AND sender_type = 'user'
                      AND is_read = 0) AS unread_count
            FROM users u
            LEFT JOIN messages m ON m.id = (
                SELECT id FROM messages WHERE telegram_id = u.telegram_id
                ORDER BY created_at DESC, id DESC LIMIT 1
            )
            ORDER BY COALESCE(m.created_at, u.created_at) DESC
        ";
        return self::connection()->query($sql)->fetchAll();
    }

    public static function markConversationRead(int $telegramId): void
    {
        $stmt = self::connection()->prepare("
            UPDATE messages SET is_read = 1
            WHERE telegram_id = :id AND sender_type = 'user' AND is_read = 0
        ");
        $stmt->execute([':id' => $telegramId]);
    }

    public static function deleteConversation(int $telegramId): void
    {
        $pdo = self::connection();
        $pdo->prepare('DELETE FROM messages WHERE telegram_id = :id')->execute([':id' => $telegramId]);
        $pdo->prepare('DELETE FROM users WHERE telegram_id = :id')->execute([':id' => $telegramId]);
    }
}
