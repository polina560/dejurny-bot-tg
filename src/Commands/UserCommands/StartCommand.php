<?php

namespace UserCommands;

use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;

class StartCommand extends UserCommand
{
    protected $name = 'start';
    protected $description = 'Стартовое меню';
    protected $usage = '/start';
    protected $version = '1.0';
    public function execute(): ServerResponse
    {
        $chat_id     = $this->getMessage()->getChat()->getId();
        $telegram_id = $this->getMessage()->getFrom()->getId();
        $config = require __DIR__ . '/../../../config.php';
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm = new \FSM($pdo);
        $stmt = $pdo->prepare("SELECT custom_name FROM `user` WHERE id = :id");
        $stmt->execute([':id' => $telegram_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($user['custom_name'])) {
            $fsm->setState($telegram_id, 'waiting_name');
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text' => "Привет! Напиши, пожалуйста, своё имя и фамилию."
            ]);
        }
        $remove_keyboard = [ "remove_keyboard" => true ];
        $remove_keyboard_json = json_encode($remove_keyboard);
        return Request::sendMessage([
            'chat_id'      => $chat_id,
            'text'         => 'Хочешь что-то сообщить? Нажми «Меню»',
            'reply_markup' => $remove_keyboard_json
        ]);
    }
}