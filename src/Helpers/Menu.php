<?php
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;

function sendMainMenu(int $chat_id): ServerResponse
{
    $keyboard = new Keyboard(
        ['Опоздание'],
        ['Ушел на больничный'],
        ['Выхожу с больничного'],
        ['Изменение расписания'],
        ['Форс-мажор'],
        ['Другое...']
    );
    $keyboard->setResizeKeyboard(true)->setOneTimeKeyboard(true);
    return Request::sendMessage([
        'chat_id' => $chat_id,
        'text' => 'Выбери действие:',
        'reply_markup' => $keyboard,
    ]);
}
if (!function_exists('navigationKeyboard')) {
    /**ну ладно
     * @param array $custom_buttons
     * @return array
     */
    function navigationKeyboard(array $custom_buttons): array
    {
        $keyboard = $custom_buttons;
        $keyboard[] = ['Назад'];
        $keyboard[] = ['Главное меню'];
        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }
}

if (!function_exists('mainMenuKeyboard')) {
    /**
     * @param array @custom_button
     * @return array
     */
    function mainMenuKeyboard(array $custom_button): array
    {
        $keyboard = $custom_button;
        $keyboard[] = ['Главное меню'];
        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }
}