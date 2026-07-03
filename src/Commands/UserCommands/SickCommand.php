<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

require_once __DIR__ . '/../../../FSM.php';
require_once __DIR__ . '/../../Helpers/Menu.php';
require_once __DIR__ . '/../../Helpers/escapeMarkdownV2.php';

use function \navigationKeyboard;
use function \mainMenuKeyboard;

class SickCommand extends UserCommand
{
    protected $name = 'sick';
    protected $description = 'Сообщить о болезни';
    protected $usage = '/sick';
    protected $version = '1.0';

    public function execute(): ServerResponse
    {
        $config = require __DIR__ . '/../../../config.php';
        file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . " START execute\n", FILE_APPEND);
        $callback = $this->getCallbackQuery();
        $message = $this->getMessage();
        file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . " callback: " . json_encode($callback) . ", message: " . json_encode($message) . "\n", FILE_APPEND);
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
            file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . " No message or callback, returning empty\n", FILE_APPEND);
            return Request::emptyResponse();
        }
        file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . " telegram_id: $telegram_id, chat_id: $chat_id, username: $username, text: '$text'\n", FILE_APPEND);
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
        $data['command'] = 'sick';
        $manager_chat_id = $config['manager_chat_id'];
        file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . " FSM state: " . json_encode($state) . ", data: " . json_encode($data) . "\n", FILE_APPEND);
        $miss_keyboard = new InlineKeyboard(
            [
                ['text' => 'Пропустить', 'callback_data' => 'skip']
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'back'],
                ['text' => '🏠 Главная', 'callback_data' => 'main'],
            ]
        );
        $keyboard = new InlineKeyboard(
            [
                ['text' => 'Да, буду работать из дома', 'callback_data' => 'yes, work in home'],
            ],
            [
                ['text' => 'Могу при необходимости', 'callback_data' => 'so so'],
            ],
            [
                ['text' => 'Нет возможности работать', 'callback_data' => 'no, work in home'],
            ],
            [
                ['text' => '⬅️ Назад', 'callback_data' => 'back'],
                ['text' => '🏠 Главная', 'callback_data' => 'main'],
            ],
        );
        if ($callback && $callback->getData() === 'back') {
            $history = $data['history'] ?? [];
            $prev = array_pop($history);
            $data['history'] = $history;
            switch ($prev) {
                case 'wait_days':
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Подскажи, ориентировочно сколько дней ты будешь отсутствовать?",
                        'reply_markup' => mainMenuKeyboard()
                    ]);
                case 'wait_remote':
                    $fsm->setState($telegram_id, 'wait_remote', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Будет ли возможность работать из дома?',
                        'reply_markup' => $keyboard,
                    ]);
                case 'wait_comment':
                    $fsm->setState($telegram_id, 'wait_comment', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Если хочешь, добавь комментарий',
                        'reply_markup' => $miss_keyboard,
                    ]);
            }
        }
        switch ($state) {
            case 'wait_days':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (!is_numeric($text)) {
                    return $this->replyToChat("Введи число.");
                } else {
                    $days = (int)$text;
                    if ($days < 1 || $days > 99) {
                        return $this->replyToChat("Слишком большое число. Скорректируй, пожалуйста");
                    }
                }
                $data['days'] = $text;
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Будет ли возможность работать из дома?',
                    'reply_markup' => $keyboard,
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_remote', $data);
                return $response;
            case 'wait_remote':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if (!$callback) {
                    return $this->replyToChat("Ошибка! Выбери вариант из кнопок!");
                }
                $cb = $callback->getData();
                if ($cb === 'yes, work in home') {
                    $data['remote'] = 'Да, буду работать из дома';
                    file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', date('c') . ", data: " . json_encode($data) . "\n", FILE_APPEND);
                } elseif ($cb === 'no, work in home') {
                    $data['remote'] = 'Нет возможности работать';
                } elseif ($cb === 'so so') {
                    $data['remote'] = 'Могу при необходимости';
                } else {
                    return $this->replyToChat("Ошибка! Выбери вариант из кнопок!");
                }
                $data['history'][] = $state;
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Если хочешь, добавь комментарий',
                    'reply_markup' => $miss_keyboard,
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_comment', $data);
                return $response;
            case 'wait_comment':
                Request::editMessageReplyMarkup([
                    'chat_id' => $chat_id,
                    'message_id' => $data['keyboard_message_id'],
                    'reply_markup' => new InlineKeyboard([])
                ]);
                if ($callback && $callback->getData() === 'skip') {
                    $data['comment'] = '';
                } else {
                    if (empty($text)) {
                        return $this->replyToChat("Ошибка! Напиши комментарий или нажми 'Пропустить'");
                    }
                    if (mb_strlen($text) > 100) {
                        return $this->replyToChat("Ошибка! Комментарий слишком длинный!");
                    }
                    $data['comment'] = $text;
                }
                file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', ", data: " . json_encode($data) . "\n", FILE_APPEND);
                $data['history'][] = $state;
                $reply = "Проверь информацию:\n\n";
                $reply .= "Ориентировочно дней отсутствия: {$data['days']}\n";
                $reply .= "Возможность работать из дома: {$data['remote']}\n";
                if (!empty($data['comment'])) {
                    $reply .= "Комментарий: {$data['comment']}\n";
                }
                $reply .= "\nВсе верно? Напиши 'Да' или 'Исправить'";
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => confirmDelayKeyboard(),
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'confirm', $data);
                return $response;
            case 'confirm':
                if ($callback && $callback->getData() === 'yes') {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    $msg = "💊 *Больничный*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $username) . "\\)\n";
                    $msg .= "Ориентировочно дней отсутствия: `" . escapeMarkdownV2($data['days']) . "`\n";
                    $msg .= "Возможность работать из дома: `" . escapeMarkdownV2($data['remote']) . "`\n";
                    if (!empty($data['comment'])) {
                        $msg .= "Комментарий: `{$data['comment']}`\n";
                    }
                    Request::sendMessage([
                        'chat_id' => $manager_chat_id,
                        'text' => $msg,
                        'parse_mode' => 'MarkdownV2'
                    ]);
                    $bd = "Ориентировочно дней отсутствия: " . $data['days'] . "\n";
                    $bd .= "Возможность работать из дома: {$data['remote']}";
                    if (!empty($data['comment'])) {
                        $bd .= "\nКомментарий: {$data['comment']}\n";
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO user_event_log (user_id, custom_name, event_type, message_content) 
                        VALUES(:user_id, :custom_name, :event_type, :message_content)");
                    $stmt->execute([
                        ':user_id' => $telegram_id,
                        ':custom_name' => $custom_name,
                        ':event_type' => 'Больничный',
                        ':message_content' => $bd
                    ]);
                    $media = $config['sick_media'][array_rand($config['sick_media'])];
                    if ($media['type'] === 'gif') {
                        Request::sendAnimation([
                            'chat_id' => $chat_id,
                            'animation' => $media['url'],
                            'caption' => 'Поправляйся, мы тебя ждём!'
                        ]);
                    } else {
                        Request::sendPhoto([
                            'chat_id' => $chat_id,
                            'photo' => $media['url'],
                            'caption' => 'Поправляйся, мы тебя ждём!'
                        ]);
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
                } elseif ($callback && $callback->getData() === 'edit') {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    $response = Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Хорошо, начнем заново. Сколько дней будешь отсутствовать?",
                        'reply_markup' => mainMenuKeyboard([]),
                    ]);
                    $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return $response;
                } else {
                    Request::editMessageReplyMarkup([
                        'chat_id' => $chat_id,
                        'message_id' => $data['keyboard_message_id'],
                        'reply_markup' => new InlineKeyboard([])
                    ]);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Нажми на кнопку 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                        'reply_markup' => confirmDelayKeyboard(),
                    ]);
                }
            default:
                $response = Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Подскажи, ориентировочно сколько дней ты будешь отсутствовать?",
                    'reply_markup' => mainMenuKeyboard()
                ]);
                $data['keyboard_message_id'] = $response->getResult()->getMessageId();
                $fsm->setState($telegram_id, 'wait_days', $data);
                return $response;
        }
    }
}