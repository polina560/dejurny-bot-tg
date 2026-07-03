<?php

namespace UserCommands;

use Longman\TelegramBot\Commands\UserCommand;
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
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $telegram_id = $message->getFrom()->getId();
        $text = trim($message->getText(true));
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'], $config['db']['user'], $config['db']['password']);
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
        $confirm_keyboard = [['Да'], ['Исправить']];
        $keyboard = [
            ['Да, буду работать из дома'], ['Могу при необходимости'],
            ['Нет возможности/не позволяет состояние работать'],
        ];
        $miss_keyboard = [['Пропустить']];
        if (mb_strtolower($text) === 'главное меню') {
            $fsm->clearState($telegram_id);
            return sendMainMenu($chat_id);
        }
        if (mb_strtolower($text) === 'назад') {
            $prev_state = $data['prev_state'] ?? null;
            if ($prev_state === 'wait_days') {
                $fsm->setState($telegram_id, 'wait_days', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Подскажи, ориентировочно сколько дней ты будешь отсутствовать?',
                    'reply_markup' => mainMenuKeyboard([]),
                ]);
            }
            if ($prev_state === 'wait_remote') {
                $fsm->setState($telegram_id, 'wait_remote', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Будет ли возможность работать из дома?',
                    'reply_markup' => navigationKeyboard($keyboard),
                ]);
            }
        }
        switch ($state) {
            case 'wait_days':
                if (!is_numeric($text)) {
                    if (mb_strlen($text) > 50) {
                        return $this->replyToChat("Ошибка! Слишком много символов, сократи сообщение.");
                    }
                } else {
                    $days = (int)$text;
                    if ($days < 1 || $days > 99) {
                        return $this->replyToChat("Слишком большое число. Скорректируй, пожалуйста");
                    }
                }
                $data['days'] = $text;
                $data['prev_state'] = 'wait_days';
                $fsm->setState($telegram_id, 'wait_remote', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Будет ли возможность работать из дома?',
                    'reply_markup' => navigationKeyboard($keyboard),
                ]);
            case 'wait_remote':
                $data['remote'] = $text;
                $data['prev_state'] = 'wait_remote';
                $valid_options = [
                    'да, буду работать из дома',
                    'могу при необходимости',
                    'нет возможности/не позволяет состояние работать'
                ];
                if (!in_array(mb_strtolower($text), $valid_options)) {
                    return $this->replyToChat("Ошибка! Выбери вариант из кнопок!");
                }
                $fsm->setState($telegram_id, 'wait_comment', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => 'Если хочешь, добавь комментарий',
                    'reply_markup' => navigationKeyboard($miss_keyboard),
                ]);
            case 'wait_comment':
                if (mb_strtolower($text) === 'пропустить') {
                    $data['comment'] = '';
                } else {
                    $data['comment'] = $text;
                    if (mb_strlen($text) > 100) {
                        return $this->replyToChat("Ошибка! Комментарий слишком длинный!");
                    }
                }
                $data['prev_state'] = 'wait_comment';
                $fsm->setState($telegram_id, 'confirm', $data);
                $reply = "Проверь информацию:\n\n";
                $reply .= "Ориентировочно дней отсутствия: {$data['days']}\n";
                $reply .= "Возможность работать из дома: {$data['remote']}\n";
                if (!empty($data['comment'])) {
                    $reply .= "Комментарий: {$data['comment']}\n";
                }
                $reply .= "\nВсе верно? Напиши 'Да' или 'Исправить'";
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => $reply,
                    'reply_markup' => mainMenuKeyboard($confirm_keyboard),
                ]);
            case 'confirm':
                if (mb_strtolower($text) === 'да') {
                    $msg = "💊 *Больничный*\n";
                    $msg .= escapeMarkdownV2($custom_name) . " \\(" . escapeMarkdownV2("@" . $message->getFrom()->getUsername()) . "\\)\n";
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
                        'text' => 'Готово! Информация передана руководству',
                    ]);
                    return sendMainMenu($chat_id);
                } elseif (mb_strtolower($text) === 'исправить') {
                    $fsm->setState($telegram_id, 'wait_days', $data);
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Хорошо, начнем заново. Сколько дней будешь отсутствовать?",
                        'reply_markup' => mainMenuKeyboard([]),
                    ]);
                } else {
                    return Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Напиши 'Да' для подтверждения или 'Исправить', чтобы внести исправления.",
                        'reply_markup' => mainMenuKeyboard($confirm_keyboard),
                    ]);
                }
            default:
                $fsm->setState($telegram_id, 'wait_days', $data);
                return Request::sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Подскажи, ориентировочно сколько дней ты будешь отсутствовать?",
                    'reply_markup' => mainMenuKeyboard([]),
                ]);
        }
    }
}