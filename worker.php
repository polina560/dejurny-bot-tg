<?php
require __DIR__ . '/vendor/autoload.php';
use Longman\TelegramBot\Telegram;
$config = require __DIR__ . '/config.php';
$telegram = new Telegram(
    $config['bot']['token'],
    $config['bot']['username']
);
$telegram->addCommandsPaths([
    __DIR__ . '/src/Commands/SystemCommands',
    __DIR__ . '/src/Commands/UserCommands',
]);
$telegram->enableMySql([
    'host'     => $config['db']['host'],
    'user'     => $config['db']['user'],
    'password' => $config['db']['password'],
    'database' => $config['db']['database'],
]);
echo "Polling started...\n";
while (true) {
    try {
        $telegram->handleGetUpdates([
            'timeout' => 30,
        ]);
    } catch (\Throwable $e) {
        file_put_contents(
            __DIR__ . '/storage/logs/errors.log',
            date('c') . ' ERROR: ' . $e->getMessage() . "\n",
            FILE_APPEND
        );
        echo "Error: " . $e->getMessage() . "\n";
        sleep(2);
    }
}