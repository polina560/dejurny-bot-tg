<?php
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;

function sendMenu(int $chat_id): ServerResponse
{
    $keyboard = new Keyboard(
        ['Да'],
        ['Исправить']
    );
    $keyboard->setResizeKeyboard(true)->setOneTimeKeyboard(false);
    return Request::sendMessage([
        'chat_id' => $chat_id,
        'text' => "Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
        'reply_markup' => $keyboard,
    ]);
}