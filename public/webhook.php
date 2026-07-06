<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
function logg($msg) {
    file_put_contents(__DIR__ . '/debug_full.log', date('c') . " " . $msg . "\n", FILE_APPEND);
}
file_put_contents(__DIR__ . '/method.log', $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
$input = file_get_contents('php://input');
logg("INPUT: " . $input);
http_response_code(200);
try {
    logg("Processing started");
    require __DIR__ . '/../vendor/autoload.php';
    logg("Autoload loaded");
    $config = require __DIR__ . '/../config.php';
    logg("Config loaded");
    $telegram = new \Longman\TelegramBot\Telegram(
        $config['bot']['token'],
        $config['bot']['username']
    );
    logg("Telegram object created");
    $telegram->addCommandsPaths([
        __DIR__ . '/../src/Commands/SystemCommands',
        __DIR__ . '/../src/Commands/UserCommands',
    ]);
    logg("Commands loaded");
    $telegram->enableMySql([
        'host'     => $config['db']['host'],
        'port'     => $config['db']['port'],
        'user'     => $config['db']['user'],
        'password' => $config['db']['password'],
        'database' => $config['db']['database'],
    ]);
    logg("Before handle");
    $telegram->handle();
    logg("After handle");
} catch (\Throwable $e) {
    logg("ERROR: " . $e->getMessage());
    logg($e->getTraceAsString());
}