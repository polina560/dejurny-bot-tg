<?php

namespace SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;

class CallbackqueryCommand extends SystemCommand
{
    protected $name = 'callbackquery';
    protected $description = 'Обработка Inline Keyboard';
    public function execute(): ServerResponse{
        $config = require __DIR__ . '/../../../config.php';
        $pdo = new \PDO(
            'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['database'],
            $config['db']['user'],
            $config['db']['password']
        );
        $fsm = new \FSM($pdo);
        $callback = $this->getCallbackQuery();
        $data = $callback->getData();
        Request::answerCallbackQuery([
            'callback_query_id' => $callback->getId(),
        ]);
        if ($data === 'delay') {
            $delayCommand = $this->telegram->getCommandObject('delay');
            $delayCommand->setUpdate($this->getUpdate());
            return $delayCommand->execute();
        } elseif (str_starts_with($data, 'delay_')) {
            $delayCommand = $this->telegram->getCommandObject('delay');
            $delayCommand->setUpdate($this->getUpdate());
            return $delayCommand->execute();
        } elseif ($data === 'sick') {
            $sickCommand  = $this->telegram->getCommandObject('sick');
            $sickCommand->setUpdate($this->getUpdate());
            return $sickCommand->execute();
        } elseif ($data === 'return_sick') {
            $return_sickCommand  = $this->telegram->getCommandObject('return_sick');
            $return_sickCommand->setUpdate($this->getUpdate());
            return $return_sickCommand->execute();
        } elseif ($data === 'schedule') {
            $scheduleCommand  = $this->telegram->getCommandObject('schedule');
            $scheduleCommand->setUpdate($this->getUpdate());
            return $scheduleCommand->execute();
        } elseif ($data === 'absence') {
            $absenceCommand  = $this->telegram->getCommandObject('absence');
            $absenceCommand->setUpdate($this->getUpdate());
            return $absenceCommand->execute();
        } elseif ($data === 'other') {
            $otherCommand  = $this->telegram->getCommandObject('other');
            $otherCommand->setUpdate($this->getUpdate());
            return $otherCommand->execute();
        } elseif ($data === 'main') {
            $fsm->clearState($callback->getFrom()->getId());
            return Request::sendMessage([
                'chat_id' => $callback->getMessage()->getChat()->getId(),
                'text' => "Хочешь отправить новое сообщение? Нажми «Меню»"
            ]);
        } elseif ($data === 'back'
            || $data === 'yes'
            || $data === 'edit'
            || $data === 'yes, work in home'
            || $data === 'no, work in home'
            || $data === 'so so'
            || $data === 'skip'
            || $data === 'today'
            || $data === 'tomorrow'
            || $data === 'hand'
            || $data === 'few_hours'
            || $data === 'all_day'
            || $data === 'some_days'
        ) {
            $telegram_id = $callback->getFrom()->getId();
            $session = $fsm->getState($telegram_id);
            $current_command = $session['data']['command'] ?? 'start';
            $command = $this->telegram->getCommandObject($current_command);
            $command->setUpdate($this->getUpdate());
            file_put_contents(__DIR__ . '/../../../storage/logs/debug_sick.log', "data: " . json_encode($data) . "\n", FILE_APPEND);
            return $command->execute();
        }
        return Request::emptyResponse();
    }
}