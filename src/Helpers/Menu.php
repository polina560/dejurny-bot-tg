<?php

use Longman\TelegramBot\Entities\InlineKeyboard;

function confirmDelayKeyboard(): InlineKeyboard
{
    return new InlineKeyboard(
        [
            ['text' => '✅ Да', 'callback_data' => 'yes'],
            ['text' => '❌ Исправить', 'callback_data' => 'edit']
        ],
        [
            ['text' => '🏠 Главная', 'callback_data' => 'main']
        ]
    );
}
function navigationKeyboard(): InlineKeyboard
{
    return new InlineKeyboard([
        ['text' => '⬅️ Назад', 'callback_data' => 'back'],
        ['text' => '🏠 Главная', 'callback_data' => 'main'],
    ]);
}
function mainMenuKeyboard(): InlineKeyboard
{
    return new InlineKeyboard([
        ['text' => '🏠 Главная', 'callback_data' => 'main'],
    ]);
}