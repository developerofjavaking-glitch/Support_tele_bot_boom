<?php
/**
 * Telegram.php — small service wrapper around the Telegram Bot API.
 */

declare(strict_types=1);

final class Telegram
{
    private static function apiBase(): string
    {
        return 'https://api.telegram.org/bot' . BOT_TOKEN . '/';
    }

    /**
     * Call any Telegram Bot API method.
     */
    public static function call(string $method, array $params = []): array
    {
        if (empty(BOT_TOKEN)) {
            return ['ok' => false, 'error' => 'BOT_TOKEN is not configured on the server.'];
        }

        $ch = curl_init(self::apiBase() . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => 'cURL error: ' . $curlErr];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'Invalid response from Telegram'];
    }

    public static function sendMessage(int $chatId, string $text): array
    {
        return self::call('sendMessage', ['chat_id' => $chatId, 'text' => $text]);
    }

    public static function getMe(): array
    {
        return self::call('getMe');
    }

    public static function setWebhook(string $url): array
    {
        return self::call('setWebhook', ['url' => $url]);
    }
}
