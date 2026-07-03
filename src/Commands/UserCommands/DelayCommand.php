<?php
namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Helpers\TelegramLogger;
require_once __DIR__ . '/../../../FSM.php';
require_once __DIR__ . '/../../Helpers/Menu.php';
require_once __DIR__ . '/../../Helpers/LimitMessage.php';
require_once __DIR__ . '/../../Helpers/TelegramLogger.php';
require_once __DIR__ . '/../../Helpers/escapeMarkdownV2.php';
use function \navigationKeyboard;
use function \mainMenuKeyboard;

class DelayCommand extends UserCommand{
    protected $name = 'delay';
    protected $description = 'Сообщить об опоздании';
    protected $usage = '/delay';
    protected $version = '1.0';

    public function execute(): ServerResponse
    {
        $config = require __DIR__ . '/../../../config.php';
        $message = $this->getMessage();
        $telegram_id = $message->getFrom()->getId();
        $chat_id = $message->getChat()->getId();
        $text = trim($message->getText(true));
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm = new \FSM($pdo);
        $stmt = $pdo->prepare("SELECT custom_name FROM `user` WHERE id= :id");
        $stmt->execute(['id' => $telegram_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $custom_name = $user['custom_name'];
        $session = $fsm->getState($telegram_id);
        $state = $session['state'] ?? null;
        $data = $session['data'] ?? [];
        $data['command'] = 'delay';
        $manager_chat_id = $config['manager_chat_id'];
        $keyboard = [
            ['Да'],
            ['Исправить']
        ];
        $time_keyboard = [
            ['10'],
            ['15'],
            ['30']
        ];
        $miss_keyboard = [['Пропустить']];
        if (mb_strtolower($text) === 'главное меню'){
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад'){
            $prev_state = $data['prev_state'] ?? null;
            if ($prev_state === 'wait_reason'){
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Укажи причину опоздания:",
                    'reply_markup' => navigationKeyboard([])
                ]);
            }
            if ($prev_state === 'wait_time'){
                $fsm->setState($telegram_id, 'wait_time', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи на сколько минут ты опаздываешь:',
                    'reply_markup' => mainMenuKeyboard($time_keyboard)
                ]);
            }
        }
        switch ($state) {
            case 'wait_reason':
                if (strlen($text) > 150) {
                    return $this->replyToChat("Ошибка! Укажи более краткую причину опоздания:");
                }
                $data['reason'] = $text;
                $data['prev_state'] = 'wait_reason';
                $fsm->setState($telegram_id, 'confirm', $data);
                $reply = "Проверь информацию: \n";
                $reply .= "Опоздание: " . $data['delay_minutes'] . " мин\n";
                $reply .= "Причина: " . $data['reason'] . "\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            case 'wait_time':
                if (!is_numeric($text)) {
                    return $this->replyToChat("Ошибка! Укажи время опоздания в минутах числом:");
                }
                if ((int)$text < 1 || (int)$text > 999) {
                    return $this->replyToChat("Ошибка! Укажи число не большее 999 минут:");
                }
                $data['delay_minutes'] = (int)$text;
                $data['prev_state'] = 'wait_time';
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Укажи причину опоздания:",
                    'reply_markup' => navigationKeyboard([])
                ]);
            case 'confirm':
                if (mb_strtolower($text) === 'да'){
                    $msg = "⏰ *Опоздание*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $message->getFrom()->getUsername()) . "\\)\n";
                    $msg .= "Опоздание на: `" . $data['delay_minutes'] . " мин`\n";
                    $msg .= "Комментарий: `" . escapeMarkdownV2($data['reason']) . "`\n";
                    $parame = [
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'MarkdownV2'
                    ];
                    $response = Request::sendMessage($parame);
                    TelegramLogger::log('sendMessage', $parame, $response, $telegram_id);
                    $bd = "Опоздание на: " . $data['delay_minutes'] . " мин\n";
                    $bd .= "Комментарий: " . $data['reason'];
                    $stmt = $pdo->prepare("
                        INSERT INTO user_event_log (user_id, custom_name, event_type, message_content) 
                        VALUES(:user_id, :custom_name, :event_type, :message_content)");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Опоздание',
                        ':message_content' => $bd
                    ]);
                    $fsm->clearState($telegram_id);
                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Готово! Информация передана руководству",
                    ]);
                    return sendMainMenu($chat_id);
                } elseif (mb_strtolower($text) === 'исправить'){
                    $fsm->setState($telegram_id, 'wait_time', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Начнем заново. Укажи на сколько минут ты опаздываешь:",
                        'reply_markup' => navigationKeyboard($time_keyboard)
                    ]);
                } else {
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Напиши 'Да' для подтверждения или 'Исправить', чтобы венсти исправления.",
                        'reply_markup' => mainMenuKeyboard($keyboard)
                    ]);
                }
            default:
                $fsm->setState($telegram_id, 'wait_time', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи на сколько минут ты опаздываешь:',
                    'reply_markup' => mainMenuKeyboard($time_keyboard)
                ]);
        }
    }
}