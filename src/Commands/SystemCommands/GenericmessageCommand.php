<?php

namespace SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
require_once __DIR__ . '/../../Helpers/Menu.php';

class GenericmessageCommand extends SystemCommand
{
    protected $name = 'genericmessage';
    protected $description = 'Роутер кнопок';

    public function execute(): ServerResponse
    {
        $chat_id = $this->getMessage()->getChat()->getId();
        $telegram_id = $this->getMessage()->getFrom()->getId();
        $text = trim($this->getMessage()->getText());
        $config = require __DIR__ . '/../../../config.php';
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm = new \FSM($pdo);
        $session = $fsm->getState($telegram_id);
        if ($session['state'] === 'waiting_name') {
            if (mb_strlen($text) < 2) {
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text'    => 'Имя слишком короткое. Пожалуйста, напиши своё настоящее имя.'
                ]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO `user` (id, custom_name)
                VALUES (:id, :custom_name)
                ON DUPLICATE KEY UPDATE custom_name = VALUES(custom_name)
            ");
            $success = $stmt->execute([
                ':id' => $telegram_id,
                ':custom_name' => $text
            ]);
            $session['data']['custom_name'] = $text;
            $fsm->setState($telegram_id, null, $session['data']);
            Request::sendMessage([
                'chat_id'      => $chat_id,
                'text'         => "Спасибо, $text! Теперь можем продолжать."
            ]);
            return Request::sendMessage([
                'chat_id'      => $chat_id,
                'text'         => "Хочешь о чем-то сообщить? Нажми «Меню»"
            ]);
        }
        if (!empty($session['data']['command'])) {
            $command = $session['data']['command'];
            return $this->telegram->executeCommand($command);
        }
        if (!empty($session['data']['custom_name'])) {
            return Request::sendMessage([
                'chat_id' => $chat_id,
                'text'    => "Привет, {$session['data']['custom_name']}! Используй меню для действий."
            ]);
        }
        return Request::emptyResponse();
    }
}