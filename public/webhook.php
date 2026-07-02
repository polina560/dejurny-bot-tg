<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/../storage/logs/php_error.log');
error_reporting(E_ALL);
file_put_contents(__DIR__ . '/__webhook_test.log', "webhook hit\n", FILE_APPEND);
file_put_contents(__DIR__ . '/__webhook_test.log', "Start\n", FILE_APPEND);
$autoload_path = __DIR__ . '/../vendor/autoload.php';
file_put_contents(__DIR__ . '/__webhook_test.log', "Trying $autoload_path\n", FILE_APPEND);
if (!file_exists($autoload_path)) {
    file_put_contents(__DIR__ . '/__webhook_test.log', "Autoload not found\n", FILE_APPEND);
    exit;
}
require $autoload_path;
file_put_contents(__DIR__ . '/__webhook_test.log', "Autoload loaded\n", FILE_APPEND);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_error.log');
error_reporting(E_ALL);
file_put_contents(
    __DIR__ . '/__webhook_test.log',
    date('c') . " webhook called\n",
    FILE_APPEND
);
require __DIR__ . '/../vendor/autoload.php';
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Exception\TelegramException;
$config = require __DIR__ . '/../config.php';
try {
    $telegram = new Telegram(
        $config['bot']['token'],
        $config['bot']['username']
    );
    $telegram->addCommandsPaths([
        __DIR__ . '/../src/Commands/SystemCommands',
        __DIR__ . '/../src/Commands/UserCommands',
    ]);
    $telegram->enableMySql([
        'host'     => $config['db']['host'],
        'port'     => $config['db']['port'],
        'user'     => $config['db']['user'],
        'password' => $config['db']['password'],
        'database' => $config['db']['database'],
    ]);
    $telegram->handle();
} catch (TelegramException $e) {
    error_log($e->getMessage());
    http_response_code(500);
}