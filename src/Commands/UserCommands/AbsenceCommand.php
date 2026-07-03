<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use function \navigationKeyboard;
use function \confirmDelayKeyboard;

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
        $manager_chat_id = $config['manager_chat_id'];
        $data = $session['data'] ?? [];
        $data['command'] = 'absence';
        $confirm_keyboard = new InlineKeyboard(
            [
                ['text' => 'В рамках нескольких часов', 'callback_data' => 'few_hours'],
            ],
            [
                ['text' => 'Весь день', 'callback_data' => 'all_day'],
            ],
            [
                ['text' => 'Несколько дней', 'callback_data' => 'some_days'],
            ],
            [
                ['text' => '🏠 Главная', 'callback_data' => 'main'],
            ],
        );
        if ($callback && $callback->getData() === 'back') {
            $history = $data['history'] ?? [];
            $prev = array_pop($history);
            $data['history'] = $history;
            switch ($prev) {
                case 'wait_hours':
                    $fsm->setState($telegram_id, 'wait_hours', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько часов ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard(),
                    ]);
                case 'wait_type':
                    $fsm->setState($telegram_id, 'wait_type', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                        'reply_markup' => $confirm_keyboard,
                    ]);
                case 'wait_days':
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько дней ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard(),
                    ]);
            }
        }
        switch ($state) {
            case 'wait_type':
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
                        'text' => "Пожалуйста, выбери временной промежуток с помощью кнопок",
                        'reply_markup' => $confirm_keyboard,
                    ]);
                }
                $cb = $callback->getData();
                if ($cb === 'few_hours') {
                    $data['type'] = 'Несколько часов';
                    $data['history'][] = $state;
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько часов ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard(),
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_hours', $data);
                    return $response;
                }
                if ($cb === 'some_days') {
                    $data['type'] = 'Несколько дней';
                    $data['history'][] = $state;
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Сколько дней ты будешь отсутствовать?',
                        'reply_markup' => navigationKeyboard(),
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return $response;
                }
                $data['type'] = 'Весь день';
                $data['duration'] = 'Весь день';
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard(),
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return $response;
            case 'wait_hours':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (!is_numeric($text)) {
                    return $this->replyToChat('Ошибка! Укажи количество часов числом:');
                }
                $data['duration'] = $text . ' ч';
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard(),
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return $response;
            case 'wait_days':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (!is_numeric($text)) {
                    return $this->replyToChat('Ошибка! Укажи количество дней числом:');
                }
                $data['duration'] = $text . ' д';
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи причину отсутствия:',
                    'reply_markup' => navigationKeyboard(),
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_reason', $data);
                return $response;
            case 'wait_reason':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                $data['reason'] = $text;
                $data['history'][] = $state;
                $reply = "Проверь информацию:\n\n";
                $reply .= "Отсутствие: {$data['type']}\n";
                $reply .= "Длительность: {$data['duration']}\n";
                $reply .= "Причина: {$data['reason']}\n\n";
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
                    $msg = "⚠️ *Отсутствие на работе*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $username) . "\\)\n";
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
                        'text' => "Готово! Информация передана руководству.",
                    ]);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Хочешь отправить новое сообщение? Нажми «Меню»"
                    ]);
                } elseif ($callback && $callback->getData() === 'edit') {
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                        'reply_markup' => $confirm_keyboard,
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_type', $data);
                    return $response;
                } else {
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Нажми на кнопку 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                        'reply_markup' => confirmDelayKeyboard(),
                    ]);
                }
            default:
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи в каких временных рамках будешь отсутствовать:',
                    'reply_markup' => $confirm_keyboard,
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_type', $data);
                return $response;
        }
    }
}