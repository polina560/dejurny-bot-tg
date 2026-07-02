<?php
namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;
use function \mainMenuKeyboard;
require_once __DIR__ . '/../../../FSM.php';

class OtherCommand extends UserCommand
{
    protected $name = 'other';
    protected $description = 'Составить другое сообщение';
    protected $usage = '/other';
    protected $version = '1.0';

    public function execute(): ServerResponse
    {
        $config = require __DIR__ . '/../../../config.php';
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText());
        $telegram_id = $message->getFrom()->getId();
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm = new \FSM($pdo);
        $session = $fsm->getState($telegram_id);
        $stmt = $pdo->prepare("SELECT custom_name FROM `user` WHERE id= :id");
        $stmt->execute(['id' => $telegram_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $custom_name = $user['custom_name'];
        $state = $session['state'] ?? null;
        $data = $session['data'] ?? [];
        $data['command'] = 'other';
        $manager_chat_id = $config['manager_chat_id'];
        $keyboard = [
            ['Да'],
            ['Исправить']
        ];
        if (mb_strtolower($text) === 'главное меню'){
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад'){
            $prev_state = $data['prev_state'] ?? 'wait_time';
            $fsm->setState($telegram_id, $prev_state, $data);
            return $this->replyToChat("Вернулись на прошлый шаг");
        }
        switch ($state) {
            case 'wait_text':
                if ($text === '') {
                    return $this->replyToChat('Укажи, что хочешь сообщить:');
                }
                if (strlen($text) > 200) {
                    return $this->replyToChat("Ошибка! Текст слишком длинный, сократи его.");
                }
                $data['text'] = $text;
                $data['prev_state'] = 'wait_text';
                $reply = "Проверь информацию:\n\n";
                $reply .= $data['text'] . "\n\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                $fsm->setState($telegram_id, 'confirm', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            case 'confirm':
                if (mb_strtolower($text) === 'да') {
                    $msg = "ℹ️ *Просто оповещение*\n";
                    $msg .= $custom_name . " (" . "@" . $message->getFrom()->getUsername() . ")\n";
                    $msg .= "`" . $data['text'] . "`" . "\n";
                    Request::sendMessage([
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'Markdown'
                    ]);
                    $stmt = $pdo->prepare("
                            INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                            VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'other',
                        ':message_content' => $data['text']
                    ]);
                    $fsm->clearState($telegram_id);
                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Готово! Информация передана руководству',
                    ]);
                    return sendMainMenu($chat_id);
                }
                if (mb_strtolower($text) === 'исправить'){
                    $fsm->setState($telegram_id, 'wait_text', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Хорошо, напиши сообщение заново:',
                        'reply_markup' => mainMenuKeyboard([]),
                    ]);
                }
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Напиши 'Да' или 'Исправить'",
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            default:
                $fsm->setState($telegram_id, 'wait_text', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Укажи, что хочешь сообщить:",
                    'reply_markup' => mainMenuKeyboard([])
                ]);
        }
    }
}