<?php
/**
 * Auth.php — minimal session-based login guard for the admin panel.
 * Credentials come from ADMIN_USERNAME / ADMIN_PASSWORD env vars.
 */

declare(strict_types=1);

final class Auth
{
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['is_admin']);
    }

    public static function attempt(string $username, string $password): bool
    {
        $userOk = hash_equals(ADMIN_USERNAME, $username);
        $passOk = hash_equals(ADMIN_PASSWORD, $password);

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_username'] = $username;
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** Redirect to login.php if not authenticated. Call at the top of protected pages. */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /** Same as requireLogin but returns a JSON 401 instead of redirecting — for api.php. */
    public static function requireLoginJson(): void
    {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
            exit;
        }
    }
}
