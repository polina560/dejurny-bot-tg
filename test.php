<?php
require __DIR__ . '/vendor/autoload.php';

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

// Конфиг бота
$bot_api_key  = '8440407204:AAEl8ZQJhH_z9kYbsnhY1UiJDJqYa_st8p8';
$bot_username = 'peppers_dezhurniy_bot';

// Создаём объект Telegram
$telegram = new Telegram($bot_api_key, $bot_username);

$chat_id = '-1003366485221';

try {
    $response = Request::sendMessage([
        'chat_id' => $chat_id,
        'text'    => "Тестовое сообщение от бота в группу ✅",
    ]);

    if ($response->isOk()) {
        echo "Сообщение успешно отправлено!\n";
    } else {
        echo "Ошибка: " . $response->getDescription() . "\n";
    }
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}