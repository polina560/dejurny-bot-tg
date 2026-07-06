<?php
echo "=== Диагностика подключения к Telegram API ===\n\n";

// Загружаем конфиг
$config = require __DIR__ . '/config.php';
$token = $config['bot']['token'];

echo "Токен: " . substr($token, 0, 10) . "...\n\n";

// Тестируем подключение через curl
$url = "https://api.telegram.org/bot{$token}/getMe";
echo "URL: $url\n\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Включаем подробный вывод
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

curl_close($ch);

echo "=== Результат ===\n";
echo "HTTP Code: $httpCode\n";
echo "Время: {$totalTime} сек\n";
echo "Ошибка curl: $error (код: $errno)\n";
echo "Ответ: $response\n\n";

if ($verboseLog) {
    echo "=== Подробный лог ===\n";
    echo $verboseLog;
}