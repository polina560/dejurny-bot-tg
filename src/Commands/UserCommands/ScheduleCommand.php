<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;

require_once __DIR__ . '/../../../FSM.php';

class ScheduleCommand extends UserCommand
{
    protected $name = 'schedule';
    protected $description = 'Сообщение об измении расписания';
    protected $usage = '/schedule';
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
        $session = $fsm->getState($telegram_id);
        $stmt = $pdo->prepare("SELECT custom_name FROM `user` WHERE id= :id");
        $stmt->execute(['id' => $telegram_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $custom_name = $user['custom_name'];
        $state = $session['state'] ?? null;
        $data = $session['data'] ?? [];
        $data['command'] = 'schedule';
        $manager_chat_id = $config['manager_chat_id'];
        $keyboard = [
            ['Да'],
            ['Исправить']
        ];
        if (mb_strtolower($text) === 'главное меню') {
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад') {
            $prev_state = $data['prev_state'] ?? 'wait_days';
            $fsm->setState($telegram_id, $prev_state, $data);
            return $this->replyToChat("Вернулись на прошлый шаг");
        }
        switch ($state) {
            case 'wait_text':
                if ($text === '') {
                    return $this->replyToChat('Опиши, что изменилось в расписании');
                }
                $data['schedule'] = $text;
                $data['prev_state'] = 'wait_text';
                if (strlen($text) > 200) {
                    return $this->replyToChat("Ошибка! Текст слишком блинный, опиши более кратко.");
                }
                $fsm->setState($telegram_id, 'confirm', $data);
                $reply = "Проверь информацию:\n\n";
                $reply .= "Изменения в расписании:\n";
                $reply .= $data['schedule'] . "\n\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            case 'confirm':
                $data['prev_state'] = 'confirm';
                if (mb_strtolower($text) === 'да') {
                    $msg = "📅 *Изменение в расписании*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $message->getFrom()->getUsername()) . "\\)\n";
                    $msg .= "Изменения в расписании:\n";
                    $msg .= "`" . escapeMarkdownV2($data['schedule']) . "`\n";
                    Request::sendMessage([
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    $bd = "Изменения в расписании: " . $data['schedule'];
                    $stmt = $pdo->prepare("
                            INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                            VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Изменение в расписании',
                        ':message_content' => $bd
                    ]);
                    $fsm->clearState($telegram_id);
                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Готово! Информация передана руководству",
                    ]);
                    return sendMainMenu($chat_id);
                }
                if (mb_strtolower($text) === 'исправить') {
                    $fsm->setState($telegram_id, 'wait_text', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Хорошо, напиши изменения заново:'
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
                    'text' => "Укажи свое новое расписание или то, что изменилось в старом:",
                    'reply_markup' => mainMenuKeyboard([])
                ]);
        }
    }
}
