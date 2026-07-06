<?php
class FSM {
    protected $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getState($telegram_id){
        $stmt = $this->pdo->prepare("SELECT state, data FROM sessions WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $row['data'] = json_decode($row['data'], true);
        }
        return $row ?: ['state' => null, 'data' => []];
    }

    public function setState($telegram_id, $state, $data = []){
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (telegram_id, state, data, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data), updated_at = NOW()
        ");
        $stmt->execute([$telegram_id, $state, $dataJson]);
    }

    public function clearState($telegram_id) {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
    }
}