<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();

return [
    'bot' => [
        'token' => $_ENV['BOT_TOKEN'] ?? '',
        'username' => $_ENV['BOT_USERNAME'] ?? ''
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'dejurny_bot',
        'user' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    'manager_chat_id' => $_ENV['MANAGER_CHAT_ID'] ?? '',
    'sick_media' => [
        ['type' => 'photo', 'url' => 'https://drive.google.com/uc?export=download&id=1saC4mY2eCE5ps_oFKDMXRD7Tj3OiZZDC'],
        ['type' => 'photo', 'url' => 'https://drive.google.com/uc?export=download&id=1ZhcBZJoA_aL992kUFOs2Sm7liDQsTTrx'],
        ['type' => 'photo', 'url' => 'https://drive.google.com/uc?export=download&id=1NIWs6F1_cugkriNI27Zv0gH4zct8VIpQ'],
        ['type' => 'gif', 'url' => 'https://drive.google.com/uc?export=download&id=1dn0o4Tvr4uC29yl1ZucwbYLshqswU8ag'],
        ['type' => 'photo', 'url' => 'https://drive.google.com/uc?export=download&id=1hxQCWUczBtaXziG54DBN6DUnE1kXcsyK'],
        ['type' => 'photo', 'url' => 'https://drive.google.com/uc?export=download&id=1FMsvRtLQYI4XCMNGR-ujGKoTj2tcaXZI'],
        ['type' => 'gif', 'url' => 'https://drive.google.com/uc?export=download&id=1sbre6e2sul8lQoz5RJJXMYAG4Lphw8-p'],
        ['type' => 'gif', 'url' => 'https://drive.google.com/uc?export=download&id=1QNVYftwJt8izzjasD44-pepKA6C9V4IB'],
        ['type' => 'gif', 'url' => 'https://drive.google.com/uc?export=download&id=1FEof_hf6Xzpyp3X2vpzrAEhPi160UqaM'],
    ],
];