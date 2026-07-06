<?php
require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$token = $config['bot']['token'];

$commands = [
    ['command' => 'start', 'description' => 'Стартовое меню'],
    ['command' => 'delay', 'description' => 'Сообщить об опоздании'],
    ['command' => 'sick', 'description' => 'Уйти на больничный'],
    ['command' => 'return_sick', 'description' => 'Выйти с больничного'],
    ['command' => 'schedule', 'description' => 'Изменение расписания'],
    ['command' => 'absence', 'description' => 'Форс-мажор / отсутствие'],
    ['command' => 'other', 'description' => 'Другое сообщение'],
    ['command' => 'menu', 'description' => 'Показать главное меню'],
];

$url = "https://api.telegram.org/bot{$token}/setMyCommands";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['commands' => $commands]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

echo "Ответ Telegram: " . $response . "\n";