<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$apiKey = 'A522A241C62CA4D78453839AE03F744E';
$matchId = $_GET['match_id'] ?? null;

if (!$matchId) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'match_id is required',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$url = 'https://api.steampowered.com/IDOTA2Match_570/GetMatchDetails/v1/?' . http_build_query([
    'key' => $apiKey,
    'match_id' => $matchId,
    'format' => 'json',
]);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HEADER => false,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'source' => 'curl',
        'curl_errno' => $curlErrno,
        'curl_error' => $curlError,
        'steam_url' => $url,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
$jsonError = json_last_error_msg();

if ($httpCode >= 400) {
    http_response_code($httpCode);
    echo json_encode([
        'ok' => false,
        'source' => 'steam',
        'http_code' => $httpCode,
        'steam_response_raw' => $response,
        'steam_response_json' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($data === null) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'source' => 'json_decode',
        'http_code' => $httpCode,
        'json_error' => $jsonError,
        'steam_response_raw' => $response,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'http_code' => $httpCode,
    'data' => $data,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);