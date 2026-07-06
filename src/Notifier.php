<?php

namespace src;
use Longman\TelegramBot\Request;

class Notifier
{
    private string $managerChatId;

    public function __construct(string $managerChatId)
    {
        $this->managerChatId = $managerChatId;
    }

    public function send(array $data): void
    {
        $text = $this->format($data);

        Request::sendMessage([
            'chat_id' => $this->managerChatId,
            'text'    => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    private function format(array $data): string
    {
        return
            "🚨 <b>ОПОВЕЩЕНИЕ</b>\n\n" .
            "👤 <b>Сотрудник:</b> {$data['name']}\n" .
            "🆔 <b>Telegram ID:</b> {$data['telegram_id']}\n" .
            "📌 <b>Тип:</b> {$data['type']}\n\n" .
            ($data['body'] ?? '') .
            "\n📅 <b>Дата:</b> " . date('d.m.Y H:i');
    }
}