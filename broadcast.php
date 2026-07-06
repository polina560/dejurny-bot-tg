<?php
$config = require __DIR__ . '/config.php';
$token = $config['bot']['token'];
$message = "🚀 Привет!\n\nДля корректной работа бота необходимо отправить /start.";
$db_host = $config['db']['host'];
$db_name = $config['db']['database'];
$db_user = $config['db']['user'];$db_pass = $config['db']['password'];
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
function sendMessage($token, $chat_id, $text) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
$stmt = $pdo->query("SELECT id FROM user");
$success = 0;
$errors = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $chat_id = $row['id'];
    $response = sendMessage($token, $chat_id, $message);
    $responseData = json_decode($response, true);
    if ($responseData['ok']) {
        $success++;
    } else {
        $errors++;
        if (isset($responseData['error_code']) && $responseData['error_code'] == 403) {
            $delete = $pdo->prepare("DELETE FROM user WHERE id = ?");
            $delete->execute([$chat_id]);
        }
    }
    usleep(200000);
}
echo "Рассылка завершена.<br>";
echo "Успешно: $success<br>";
echo "Ошибки: $errors<br>";