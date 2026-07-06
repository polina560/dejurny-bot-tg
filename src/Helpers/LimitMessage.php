<?php
function checkDailyLimit(PDO $pdo, int $telegram_id, int $max_messages = 3): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt
        FROM user_event_log
        WHERE user_id = :user_id
          AND created_at >= NOW() - INTERVAL 1 DAY
    ");
    $stmt->execute([':user_id' => $telegram_id]);
    $count = (int)$stmt->fetchColumn();

    return $count < $max_messages;
}