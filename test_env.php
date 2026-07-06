<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "=== Проверка .env ===\n\n";

// Проверяем, существует ли файл
if (file_exists(__DIR__ . '/.env')) {
    echo "✅ Файл .env существует\n";
    echo "Содержимое:\n";
    echo file_get_contents(__DIR__ . '/.env') . "\n\n";
} else {
    echo "❌ Файл .env НЕ найден!\n";
    echo "Путь: " . __DIR__ . '/.env' . "\n\n";
}

// Загружаем .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

echo "=== Переменные окружения ===\n";
echo "TOKEN: " . ($_ENV['BOT_TOKEN'] ?? 'НЕ НАЙДЕН') . "\n";
echo "USERNAME: " . ($_ENV['BOT_USERNAME'] ?? 'НЕ НАЙДЕН') . "\n";
echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? 'НЕ НАЙДЕН') . "\n";
echo "DB_DATABASE: " . ($_ENV['DB_DATABASE'] ?? 'НЕ НАЙДЕН') . "\n";

echo "\n=== Загрузка config.php ===\n";
$config = require __DIR__ . '/config.php';
echo "Token из config: " . ($config['bot']['token'] ?: 'ПУСТО') . "\n";
echo "Username из config: " . ($config['bot']['username'] ?: 'ПУСТО') . "\n";