<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;
use function \mainMenuKeyboard;

require_once __DIR__ . '/../../../FSM.php';

class AbsenceCommand extends UserCommand
{
    protected $name = 'absence';
    protected $description = 'Сообщить об отсутствии на работе(форс-мажор)';
    protected $usage = '/absence';
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
        $manager_chat_id = $config['manager_chat_id'];
        $data = $session['data'] ?? [];
        $data['command'] = 'absence';
        $keyboard = [
            ['Да'],
            ['Исправить']
        ];
        $confirm_keyboard = [
            ['В рамках нескольких часов'],
            ['Весь день'],
            ['Несколько дней'],
        ];
        if (mb_strtolower($text) === 'главное меню') {
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад') {
            if (!empty($session['data']['prev_state'])) {
                $prev_state = $session['data']['prev_state'];
                $fsm->setState($telegram_id, $prev_state, $session['data']);
                switch ($prev_state) {
                    case 'wait_hours':
                        return Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Сколько часов ты будешь отсутствовать?',
                            'reply_markup' => navigationKeyboard([]),
                        ]);
                    case 'wait_type':
                        return Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                            'reply_markup' => mainMenuKeyboard($confirm_keyboard),
                        ]);
                    case 'wait_days':
                        return Request::sendMessage([
                            'chat_id' => $chat_id,
                            'text' => 'Сколько дней ты будешь отсутствовать?',
                            'reply_markup' => navigationKeyboard([]),
                        ]);
                }
            }
        }
        switch ($state) {
            case 'wait_type':
                $data['type'] = $text;
                if (!in_array($text, ['В рамках нескольких часов', 'Несколько дней', 'Весь день'])) {
                    return $this->replyToChat("Пожалуйста, выбери временной промежуток с помощью кнопок ниже.");
                }
                if ($text === 'В рамках нескольких часов') {
                    $data['prev_state'] = 'wait_type';
                    $fsm->setState($telegram_id, 'wait_hours', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько часов ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard([]),
                    ]);
                }
                if ($text === 'Несколько дней') {
                    $data['prev_state'] = 'wait_type';
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько дней ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard([]),
                    ]);
                }
                $data['duration'] = 'Весь день';
                $data['prev_state'] = 'wait_type';
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard([]),
                ]);
            case 'wait_hours':
                if (!is_numeric($text)) {
                    return $this->replyToChat('Ошибка! Укажи количество часов числом:');
                }
                $data['duration'] = $text . ' ч';
                $data['prev_state'] = 'wait_hours';
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard([]),
                ]);
            case 'wait_days':
                if (!is_numeric($text)) {
                    return $this->replyToChat('Ошибка! Укажи количество дней числом:');
                }
                $data['duration'] = $text . ' д';
                $data['prev_state'] = 'wait_days';
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard([]),
                ]);
            case 'wait_reason':
                $data['reason'] = $text;
                $data['prev_state'] = 'wait_reason';
                $fsm->setState($telegram_id, 'confirm', $data);
                $reply = "Проверь информацию:\n\n";
                $reply .= "Отсутствие: {$data['type']}\n";
                $reply .= "Длительность: {$data['duration']}\n";
                $reply .= "Причина: {$data['reason']}\n\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            case 'confirm':
                if (mb_strtolower($text) === 'да') {
                    $msg = "⚠️ *Отсутствие на работе*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $message->getFrom()->getUsername()) . "\\)\n";
                    $msg .= "Тип: `" . escapeMarkdownV2($data['type']) . "`\n";
                    $msg .= "Длительность: `" . escapeMarkdownV2($data['duration']) . "`\n";
                    $msg .= "Причина: `" . escapeMarkdownV2($data['reason']) . "`\n";
                    Request::sendMessage([
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    $bd = "Тип: {$data['type']}\n";
                    $bd .= "Длительность: {$data['duration']}\n";
                    $bd .= "Причина: {$data['reason']}";
                    $stmt = $pdo->prepare("
                            INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                            VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Отсутствие на работе',
                        ':message_content' => $bd
                    ]);
                    $fsm->clearState($telegram_id);
                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Готово! Информация передана руководству',
                    ]);
                    return sendMainMenu($chat_id);
                }
                if (mb_strtolower($text) === 'исправить') {
                    $fsm->setState($telegram_id, 'wait_type', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                        'reply_markup' => mainMenuKeyboard($confirm_keyboard),
                    ]);
                }
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Напиши 'Да' или 'Исправить'",
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
            default:
                $fsm->setState($telegram_id, 'wait_type', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                    'reply_markup' => mainMenuKeyboard($confirm_keyboard),
                ]);
        }
    }
}