<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;
use function \mainMenuKeyboard;

require_once __DIR__ . '/../../../FSM.php';

class ReturnSickCommand extends UserCommand
{
    protected $name = 'return_sick';
    protected $description = 'Сообщение о выходе с больничного';
    protected $usage = '/return_sick';
    protected $version = '1.0';

    public function execute(): ServerResponse
    {
        $config = require __DIR__ . '/../../../config.php';
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $telegram_id = $message->getFrom()->getId();
        $text = trim($message->getText(true));
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $fsm = new \FSM($pdo);
        $session = $fsm->getState($telegram_id);
        $stmt = $pdo->prepare("SELECT custom_name FROM `user` WHERE id= :id");
        $stmt->execute(['id' => $telegram_id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        $custom_name = $user['custom_name'];
        $state = $session['state'] ?? null;
        $data = $session['data'] ?? [];
        $data['command'] = 'return_sick';
        $manager_chat_id = $config['manager_chat_id'];
        $keyboard = [
            ['Сегодня', 'Завтра'],
            ['Выберите дату вручную']
        ];
        if (mb_strtolower($text) === 'главное меню') {
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад') {
            if (!empty($session['data']['prev_state'])) {
                $prev_state = $session['data']['prev_state'];
                $fsm->setState($telegram_id, $prev_state, $session['data']);
                if ($prev_state === 'wait_data') {
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи дату выхода с больничного:',
                        'reply_markup' => mainMenuKeyboard($keyboard),
                    ]);
                }
            }
        }
        switch ($state) {
            case 'wait_data':
                if (!in_array($text, ['Сегодня', 'Завтра', 'Выберите дату вручную'])) {
                    return $this->replyToChat("Пожалуйста, выбери дату с помощью кнопок ниже.");
                }
                if ($text === 'Сегодня') {
                    $date = date("d.m");
                } elseif ($text === 'Завтра') {
                    $date = date("d.m", strtotime('+1 day'));
                } elseif ($text === 'Выберите дату вручную') {
                    $data['prev_state'] = 'wait_data';
                    $fsm->setState($telegram_id, 'wait_manual_date', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Напиши дату выхода с больничного в формате ДД.ММ",
                        'reply_markup' => navigationKeyboard([])
                    ]);
                } else {
                    $date = $text;
                }
                $data['prev_state'] = 'wait_data';
                $msg = "🚨 *Выход с больничного*\n";
                $msg .= $custom_name . " (" . "@" . $message->getFrom()->getUsername() . ")\n";
                $msg .= "Дата выхода: `{$date}`\n";
                Request::sendMessage([
                    'chat_id' => $manager_chat_id,
                    'text' => $msg,
                    'parse_mode' => 'Markdown'
                ]);
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                        VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'return_sick',
                        ':message_content' => "Дата выхода: {$date}"
                    ]);
                } catch (\PDOException $e) {
                    error_log("DB ERROR: " . $e->getMessage());
                }
                $fsm->clearState($telegram_id);
                Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Готово! Информация передана руководству',
                ]);
                return sendMainMenu($chat_id);
            case 'wait_manual_date':
                if (!preg_match('/^(0[1-9]|[12][0-9]|3[01])\.(0[1-9]|1[0-2])$/', $text)) {
                    return $this->replyToChat("Неверный формат. Введи дату в формате ДД.MM:");
                }
                list($day, $month) = explode('.', $text);
                $year = date('Y');
                if (!checkdate((int)$month, (int)$day, (int)$year)) {
                    return $this->replyToChat("Такой даты не существует. Попробуй снова.");
                }
                $inputDate = strtotime("$year-$month-$day");
                $today = strtotime(date("Y-m-d"));
                if ($inputDate < $today) {
                    return $this->replyToChat("Дата должна быть сегодня или позже.");
                }
                $date = $text;
                $data['prev_state'] = 'wait_manual_date';
                $msg = "🚨 *Выход с больничного*\n";
                $msg .= $custom_name . " (" . "@" . $message->getFrom()->getUsername() . ")\n";
                $msg .= "Дата выхода: `{$date}`\n";
                Request::sendMessage([
                    'chat_id' => $manager_chat_id,
                    'text' => $msg,
                    'parse_mode' => 'Markdown'
                ]);
                $bd = "Дата выхода: {$date}";
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_event_log (user_id, custom_name, event_type, message_content) 
                        VALUES(:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'return_sick',
                        ':message_content' => $bd
                    ]);
                } catch (\PDOException $e) {
                    error_log("DB ERROR: " . $e->getMessage());
                }
                $fsm->clearState($telegram_id);
                Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Готово! Информация передана руководству',
                ]);
                return sendMainMenu($chat_id);
            default:
                $fsm->setState($telegram_id, 'wait_data', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи дату выхода с больничного:',
                    'reply_markup' => mainMenuKeyboard($keyboard),
                ]);
        }
    }
}