<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Helpers\TelegramLogger;
use function \navigationKeyboard;
use function \mainMenuKeyboard;
use function \confirmDelayKeyboard;
require_once __DIR__ . '/../../../FSM.php';
require_once __DIR__ . '/../../Helpers/Menu.php';
require_once __DIR__ . '/../../Helpers/LimitMessage.php';
require_once __DIR__ . '/../../Helpers/TelegramLogger.php';
require_once __DIR__ . '/../../Helpers/escapeMarkdownV2.php';
class DelayCommand extends UserCommand
{
    protected $name = 'delay';
    protected $description = 'Сообщить об опоздании';
    protected $usage = '/delay';
    protected $version = '1.0';
    public function execute(): ServerResponse
    {
        $config = require __DIR__ . '/../../../config.php';
        file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " START execute\n", FILE_APPEND);
        $callback = $this->getCallbackQuery();
        $message = $this->getMessage();
        file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " callback: " . json_encode($callback) . ", message: " . json_encode($message) . "\n", FILE_APPEND);
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
            file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " No message or callback, returning empty\n", FILE_APPEND);
            return Request::emptyResponse();
        }
        file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " telegram_id: $telegram_id, chat_id: $chat_id, username: $username, text: '$text'\n", FILE_APPEND);
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
        file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " FSM state: " . json_encode($state) . ", data: " . json_encode($data) . "\n", FILE_APPEND);
        $number_keyboard = new InlineKeyboard(
            [
                ['text' => '10', 'callback_data' => 'delay_10'],
                ['text' => '15', 'callback_data' => 'delay_15'],
                ['text' => '30', 'callback_data' => 'delay_30'],
            ],
            [
                ['text' => '🏠 Главная', 'callback_data' => 'main'],
            ]
        );
        if ($callback && $callback->getData() === 'back') {
            $history = $data['history'] ?? [];
            $prev = array_pop($history);
            $data['history'] = $history;
            if ($prev == 'wait_time') {
                $fsm->setState($telegram_id, 'wait_time', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи на сколько минут ты опаздываешь:',
                    'reply_markup' => $number_keyboard
                ]);
            }
        }
        switch ($state) {
            case 'wait_reason':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (strlen($text) > 150) {
                    return $this->replyToChat("Ошибка! Укажи более краткую причину опоздания:");
                }
                $data['reason'] = $text;
                $reply = "Проверь информацию: \n";
                $reply .= "Опоздание: " . $data['delay_minutes'] . " мин\n";
                $reply .= "Причина: " . $data['reason'] . "\n";
                $reply .= "Все верно? Напиши 'Да' или 'Исправить'";
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => confirmDelayKeyboard()
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'confirm', $data);
                return $response;
            case 'wait_time':
                if ($callback && str_starts_with($callback->getData(), 'delay_')) {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    $minutes = (int)str_replace('delay_', '', $callback->getData());
                    $data['delay_minutes'] = $minutes;
                    file_put_contents(__DIR__ . '/debug_delay.log', date('c') . ", data: " . json_encode($data) . "\n", FILE_APPEND);
                    $data['history'][] = $state;
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи причину опоздания:',
                        'reply_markup' => navigationKeyboard()
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_reason', $data);
                    return $response;
                }
                if ($text !== '') {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    if (!is_numeric($text)) {
                        return $this->replyToChat("Ошибка! Укажи время опоздания в минутах числом:");
                    }
                    $minutes = (int)$text;
                    if ($minutes < 1 || $minutes > 999) {
                        return $this->replyToChat("Ошибка! Укажи число не большее 999 минут:");
                    }
                    $data['delay_minutes'] = $minutes;
                    $data['history'][] = $state;
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Укажи причину опоздания:',
                        'reply_markup' => navigationKeyboard()
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_reason', $data);
                    return $response;
                }
                return Request::emptyResponse();
            case 'confirm':
                if ($callback && $callback->getData() === 'yes') {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    $msg = "⏰ *Опоздание*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $username) . "\\)\n";
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
                        'text' => "Готово! Информация передана руководству.",
                    ]);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Хочешь отправить новое сообщение? Нажми «Меню»"
                    ]);
                } elseif ($callback && $callback->getData() === 'edit') {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    $response =  Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Начнем заново. Укажи на сколько минут ты опаздываешь:",
                        'reply_markup' => $number_keyboard
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_time', $data);
                    return $response;
                } else {
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Нажми на кнопку 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                        'reply_markup' => confirmDelayKeyboard()
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    return $response;
                }
            default:
                file_put_contents(__DIR__ . '/debug_delay.log', date('c') . " default: show number_keyboard, message_id: $message_id\n", FILE_APPEND);
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Укажи на сколько минут ты опаздываешь:',
                    'reply_markup' => $number_keyboard
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_time', $data);
                file_put_contents(__DIR__ . '/debug_delay.log', date('c') . ", data: " . json_encode($data) . "\n", FILE_APPEND);
                return $response;
        }
    }
}