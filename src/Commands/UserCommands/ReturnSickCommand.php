<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
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
        $callback = $this->getCallbackQuery();
        $message = $this->getMessage();
        if ($message) {
            $message_id = $message->getMessageId();
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
        $keyboard = new InlineKeyboard(
            [
                ['text' => 'Сегодня', 'callback_data' => 'today'],
                ['text' => 'Завтра', 'callback_data' => 'tomorrow'],
            ],
            [
                ['text' => 'Выберите дату вручную', 'callback_data' => 'hand'],
            ],
            [
                ['text' => '🏠 Главная', 'callback_data' => 'main'],
            ]
        );
        if ($callback && $callback->getData() === 'back') {
            $history = $data['history'] ?? [];
            $prev = array_pop($history);
            $data['history'] = $history;
            if ($prev == 'wait_data') {
                $fsm->setState($telegram_id, 'wait_data', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи дату выхода с больничного:',
                    'reply_markup' => $keyboard,
                ]);
            }
        }
        switch ($state) {
            case 'wait_data':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (!$callback) {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Пожалуйста, выбери дату используя кнопки.",
                        'reply_markup' => $keyboard,
                    ]);
                }
                if ($callback->getData() === 'today') {
                    $date = date("d.m");
                } elseif ($callback->getData() === 'tomorrow') {
                    $date = date("d.m", strtotime('+1 day'));
                } elseif ($callback->getData() === 'hand') {
                    $data['history'][] = $state;
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Напиши дату выхода с больничного в формате ДД.ММ",
                        'reply_markup' => navigationKeyboard([])
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_manual_date', $data);
                    return $response;
                }
                $data['history'][] = $state;
                $msg = "💪 *Выход с больничного*\n";
                $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $username) . "\\)\n";
                $msg .= "Дата выхода: `" . escapeMarkdownV2($date) . "`\n";
                Request::sendMessage([
                    'chat_id' => $manager_chat_id,
                    'text' => $msg,
                    'parse_mode' => 'MarkdownV2'
                ]);
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_event_log (user_id, custom_name, event_type, message_content)
                        VALUES (:user_id, :custom_name, :event_type, :message_content)
                    ");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Выход с больничного',
                        ':message_content' => "Дата выхода: {$date}"
                    ]);
                } catch (\PDOException $e) {
                    error_log("DB ERROR: " . $e->getMessage());
                }
                $fsm->clearState($telegram_id);
                Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Готово! Информация передана руководству.",
                ]);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Хочешь отправить новое сообщение? Нажми «Меню»"
                ]);
            case 'wait_manual_date':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
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
                $msg = "💪 *Выход с больничного*\n";
                $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $username) . "\\)\n";
                $msg .= "Дата выхода: `" . escapeMarkdownV2($date) . "`\n";
                Request::sendMessage([
                    'chat_id' => $manager_chat_id,
                    'text' => $msg,
                    'parse_mode' => 'MarkdownV2'
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
                        ':event_type' => 'Выход с больничного',
                        ':message_content' => $bd
                    ]);
                } catch (\PDOException $e) {
                    error_log("DB ERROR: " . $e->getMessage());
                }
                $fsm->clearState($telegram_id);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Готово! Информация передана руководству.\nХочешь отправить новое сообщение? Нажми «Меню»",
                ]);
            default:
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи дату выхода с больничного:',
                    'reply_markup' => $keyboard,
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_data', $data);
                return $response;

        }
    }
}