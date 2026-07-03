<?php

namespace UserCommands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{
    protected $name = 'start';
    protected $description = 'Стартовое меню';
    protected $usage = '/start';
    protected $version = '1.0';
    public function execute(): ServerResponse
    {
        $chat_id = $this->getMessage()->getChat()->getId();
        return Request::sendMessage([
            'chat_id' => $chat_id,
            'text' => 'WORKS'
        ]);
    }
}