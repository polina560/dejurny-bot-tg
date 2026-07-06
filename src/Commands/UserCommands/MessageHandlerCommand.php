<?php

namespace UserCommands;

use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;

class MessageHandlerCommand extends UserCommand
{
    protected $name = 'messagehandler';
    protected $description = 'Обрабатывает ввод имени';
    protected $usage = '';
    protected $version = '1.0';

    public function execute(): ServerResponse
    {
        $chat_id     = $this->getMessage()->getChat()->getId();
        $telegram_id = $this->getMessage()->getFrom()->getId();
        $text        = trim($this->getMessage()->getText());
        $config = require __DIR__ . '/../../../config.php';
        $pdo    = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm     = new \FSM($pdo);
        $session = $fsm->getState($telegram_id);
        if ($session['state'] === 'waiting_name') {
            if (mb_strlen($text) < 2) {
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text'    => 'Имя слишком короткое. Напиши ещё раз.'
                ]);
            }
            $stmt = $pdo->prepare("
                INSERT INTO `user` (id, custom_name)
                VALUES (:id, :custom_name)
                ON DUPLICATE KEY UPDATE custom_name = :custom_name
            ");
            $stmt->execute([
                ':id'          => $telegram_id,
                ':custom_name' => $text
            ]);
            $fsm->clearState($telegram_id);
            return Request::sendMessage([
                'chat_id'      => $chat_id,
                'text'         => "Хочешь отправить новое сообщение? Нажми «Меню»",
            ]);
        }
        return Request::emptyResponse();
    }
}
