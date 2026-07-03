<?php

namespace Helpers;

use Longman\TelegramBot\Entities\ServerResponse;

class TelegramLogger
{
    private static string $file = __DIR__ . '/../../storage/logs/telegram.log';

    public static function log(
        string         $method,
        array          $payload,
        ServerResponse $response,
        int            $userId = 0
    ): void
    {
        $row = [
            'time' => date('Y-m-d H:i:s'),
            'method' => $method,
            'user_id' => $userId,
            'chat_id' => $payload['chat_id'] ?? null,
            'ok' => $response->isOk(),
            'error_code' => $response->getErrorCode(),
            'description' => $response->getDescription(),
            'message_id' => $response->isOk() ? $response->getResult()->getMessageId() : null,
            'payload' => self::shorten($payload),
        ];

        file_put_contents(
            self::$file,
            json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function shorten(array $payload): array
    {
        if (isset($payload['text'])) {
            $payload['text'] = mb_substr($payload['text'], 0, 1000);
        }
        return $payload;
    }
}
