<?php
putenv('ALL_PROXY=socks5://127.0.0.1:12334');
putenv('HTTP_PROXY=http://127.0.0.1:12334');
putenv('HTTPS_PROXY=http://127.0.0.1:12334');
putenv('NO_PROXY=');

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$telegram = new \Longman\TelegramBot\Telegram(
    $config['bot']['token'],
    $config['bot']['username']
);

$telegram->enableMySql($config['db']);

$telegram->addCommandsPaths([
    __DIR__ . '/src/Commands/SystemCommands',
    __DIR__ . '/src/Commands/UserCommands',
]);

\Longman\TelegramBot\Request::deleteWebhook();

echo "✅ Бот запущен локально в режиме Long Polling.\n";
echo "Нажмите Ctrl+C в терминале, чтобы остановить.\n\n";

$offset = 0;

while (true) {
    try {
        $response = \Longman\TelegramBot\Request::getUpdates([
            'offset'  => $offset,
            'limit'   => 1,
            'timeout' => 10,
        ]);

        if ($response->isOk()) {
            $updates = $response->getResult();
            foreach ($updates as $update) {
                $offset = $update->getUpdateId() + 1;
                echo "[" . date('H:i:s') . "] Получено обновление #" . $update->getUpdateId() . "\n";
                $telegram->processUpdate($update);
            }
        } else {
            echo "Ошибка API: " . $response->getDescription() . "\n";
            sleep(3);
        }
    } catch (Exception $e) {
        echo "❌ Исключение: " . $e->getMessage() . "\n";
        echo "   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
        $offset++;
        sleep(2);
    }
}