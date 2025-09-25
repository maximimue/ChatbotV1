#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI-Skript zum Prüfen der Antwortlatenz der Chat-API.
 */

$options = getopt('', ['config::', 'question::']);
$configPath = isset($options['config']) ? (string)$options['config'] : null;
$question = isset($options['question']) ? trim((string)$options['question']) : 'PING';

if ($configPath !== null && $configPath !== '') {
    if (!file_exists($configPath)) {
        fwrite(STDERR, "Konfigurationsdatei nicht gefunden: {$configPath}\n");
        exit(1);
    }
    $configPath = realpath($configPath) ?: $configPath;
    $GLOBALS['configPath'] = $configPath;
}

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../http_client.php';

if (!isset($API_URL) || !$API_URL) {
    fwrite(STDERR, "API_URL ist nicht konfiguriert.\n");
    exit(1);
}

$payload = [
    'messages' => [
        ['role' => 'system', 'content' => 'Health check ping'],
        ['role' => 'user', 'content' => $question !== '' ? $question : 'PING'],
    ],
    'conversation_id' => bin2hex(random_bytes(8)),
];

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
if ($payloadJson === false) {
    fwrite(STDERR, "Payload konnte nicht kodiert werden.\n");
    exit(1);
}

$apiOptions = [
    'max_attempts' => 3,
    'connect_timeout' => 5,
    'timeout' => 15,
    'backoff_initial_ms' => 200,
    'backoff_factor' => 2.0,
];

if (isset($API_ERROR_LOG) && is_string($API_ERROR_LOG) && $API_ERROR_LOG !== '') {
    $apiOptions['error_log_path'] = $API_ERROR_LOG;
}

$start = microtime(true);
$result = chatbot_post_json_with_retry($API_URL, $payloadJson, $apiOptions);
$latencyMs = (microtime(true) - $start) * 1000;

$statusCode = $result['status_code'] ?? null;
$success = false;
$errorCode = $result['error_code'] ?? null;
$responseExcerpt = chatbot_truncate_for_log($result['body'] ?? null);

if ($result['success']) {
    $decoded = json_decode($result['body'] ?? '', true);
    if (is_array($decoded) && isset($decoded['answer']) && !isset($decoded['error'])) {
        $success = true;
        if ($responseExcerpt === null && isset($decoded['answer'])) {
            $responseExcerpt = chatbot_truncate_for_log((string)$decoded['answer']);
        }
    } else {
        $success = false;
        $errorCode = is_array($decoded) && isset($decoded['error_code'])
            ? (string)$decoded['error_code']
            : 'API_INVALID_RESPONSE';
        if ($responseExcerpt === null) {
            $responseExcerpt = chatbot_truncate_for_log($result['body'] ?? null);
        }
    }
} else {
    $success = false;
}

if ($db instanceof PDO) {
    try {
        $stmt = $db->prepare('INSERT INTO health_checks (status_code, latency_ms, success, error_code, response_excerpt) VALUES (:status_code, :latency_ms, :success, :error_code, :response_excerpt)');
        $stmt->execute([
            ':status_code' => $statusCode,
            ':latency_ms' => $latencyMs,
            ':success' => $success ? 1 : 0,
            ':error_code' => $errorCode,
            ':response_excerpt' => $responseExcerpt,
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, "Health-Check konnte nicht protokolliert werden: " . $e->getMessage() . "\n");
    }
} else {
    fwrite(STDERR, "Warnung: Keine Datenbankverbindung – Health-Check wird nicht gespeichert.\n");
}

if ($success) {
    $latencyFormatted = number_format($latencyMs, 1, ',', '.');
    echo "Health-Check erfolgreich: {$latencyFormatted} ms (HTTP " . ($statusCode ?? 0) . ")\n";
    exit(0);
}

$latencyFormatted = number_format($latencyMs, 1, ',', '.');
$codeText = $errorCode ?? 'API_REQUEST_FAILED';
fwrite(STDERR, "Health-Check fehlgeschlagen ({$codeText}) nach {$latencyFormatted} ms" . ($statusCode ? " – HTTP {$statusCode}" : '') . "\n");
exit(1);
