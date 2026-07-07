<?php
namespace UserCommands;

use http\Env\Response;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;
use function \mainMenuKeyboard;
use function \confirmDelayKeyboard;
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
        $callback = $this->getCallbackQuery();
        if ($message) {
            $message_id = $message->getChat()->getId();
            $telegram_id = $message->getFrom()->getId();
            $chat_id = $message->getChat()->getId();
            $username = $message->getFrom()->getUsername();
            $text = trim($message->getText(true));
        } elseif ($callback) {
            $message_id = $callback->getMessage()->getMessageId();
            $telegram_id = $callback->getFrom()->getId();
            $chat_id = $callback->getMessage()->getChat()->getId();
            $username = $callback->getFrom()->getUsername();
            $text = '';
        } else {
            return Request::emptyResponse();
        }
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
        switch ($state) {
            case 'wait_text':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if ($text === '') {
                    return $this->replyToChat('Укажи, что хочешь сообщить:');
                }
                if (strlen($text) > 200) {
                    return $this->replyToChat("Ошибка! Текст слишком длинный, сократи его.");
                }
                $data['text'] = $text;
                $reply = "Проверь информацию:\n\n";
                $reply .= $data['text'] . "\n\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => confirmDelayKeyboard(),
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'confirm', $data);
                return $response;
            case 'confirm':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if ($callback && $callback->getData() === 'yes') {
                    $msg = "🔔 *Просто оповещение*\n";
                    $msg .= escapeMarkdownV2($custom_name ?? '') . " \\(" . escapeMarkdownV2("@" . ($username ?? '')) . "\\)\n";
                    $msg .= "`" . escapeMarkdownV2($data['text'] ?? '') . "`\n";
                    Request::sendMessage([
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    $stmt = $pdo->prepare("
                            INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                            VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Просто оповещение',
                        ':message_content' => $data['text']
                    ]);
                    $fsm->clearState($telegram_id);
                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Готово! Информация передана руководству.",
                    ]);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Хочешь отправить новое сообщение? Нажми «Меню»"
                    ]);
                } elseif ($callback && $callback->getData() === 'edit') {
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Хорошо, напиши сообщение заново:',
                        'reply_markup' => mainMenuKeyboard(),
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_text', $data);
                    return $response;
                } else {
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Нажми на кнопку 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                        'reply_markup' => confirmDelayKeyboard()
                    ]);
                }
            default:
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Укажи, что хочешь сообщить:",
                    'reply_markup' => mainMenuKeyboard()
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_text', $data);
                return $response;
        }
    }
}