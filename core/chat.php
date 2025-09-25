<?php
/**
 * Endpunkt zur Verarbeitung von Chat‑Anfragen.
 *
 * Dieses Skript wird vom Frontend via Fetch aufgerufen. Es erwartet ein JSON‑Objekt mit
 * dem Schlüssel „question“ und gibt ein JSON mit „answer“ und optional „sources“ zurück.
 * Für die tatsächliche Beantwortung der Anfrage wird eine externe API verwendet, deren
 * URL in der hotelspezifischen config.php als $API_URL definiert ist.
 */

header('Content-Type: application/json; charset=utf-8');

// Konfiguration und Datenbank laden
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/sanitizer.php';
require_once __DIR__ . '/http_client.php';

/**
 * Erstellt eine benutzerfreundliche Fehlermeldung inklusive Support-Hinweis.
 *
 * @param string|null $technicalCode
 * @return array{code:string,message:string,support_hint:string}
 */
function chatbot_build_user_error(?string $technicalCode): array
{
    $supportHint = 'Bitte versuchen Sie es in ein paar Minuten erneut. Sollte das Problem weiterhin bestehen, kontaktieren Sie bitte unser Support-Team.';

    $messages = [
        'API_TIMEOUT' => 'die Verbindung zu unserem Wissensdienst hat zu lange gedauert.',
        'API_CONNECTION_ERROR' => 'die Verbindung zu unserem Wissensdienst konnte nicht aufgebaut werden.',
        'API_TRANSPORT_ERROR' => 'bei der Verbindung zu unserem Wissensdienst ist ein Transportfehler aufgetreten.',
        'API_HTTP_429' => 'unser Wissensdienst ist kurzzeitig ausgelastet.',
        'API_HTTP_5XX' => 'der Wissensdienst meldet derzeit einen Serverfehler.',
        'API_INVALID_RESPONSE' => 'die Antwort unseres Wissensdienstes war unvollständig.',
        'API_MALFORMED_RESPONSE' => 'die Antwort unseres Wissensdienstes hatte ein unerwartetes Format.',
        'API_NO_STATUS' => 'der Wissensdienst hat keinen Status zurückgeliefert.',
        'API_INIT_ERROR' => 'die Anfrage an den Wissensdienst konnte nicht vorbereitet werden.',
    ];

    $code = $technicalCode ?? 'API_REQUEST_FAILED';
    $messageDetail = $messages[$code] ?? 'es ist ein technisches Problem aufgetreten.';

    return [
        'code' => $code,
        'message' => 'Entschuldigung, ' . $messageDetail . ' ' . $supportHint,
        'support_hint' => $supportHint,
    ];
}

// Eingabedaten auslesen
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
$question = isset($inputData['question']) ? trim($inputData['question']) : '';
$conversationId = '';
if (isset($inputData['conversation_id'])) {
    $conversationIdCandidate = trim((string)$inputData['conversation_id']);
    if ($conversationIdCandidate !== '') {
        $conversationId = preg_replace('/[^a-zA-Z0-9_-]/', '', $conversationIdCandidate);
        $conversationId = substr($conversationId, 0, 64);
    }
}

$history = [];
$historyInput = $inputData['history'] ?? [];
if (is_array($historyInput)) {
    foreach ($historyInput as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $role = isset($entry['role']) ? strtolower(trim((string)$entry['role'])) : '';
        $content = isset($entry['content']) ? trim((string)$entry['content']) : '';
        if ($content === '') {
            continue;
        }
        if ($role === 'bot') {
            $role = 'assistant';
        }
        if (!in_array($role, ['user', 'assistant', 'system'], true)) {
            continue;
        }
        $history[] = ['role' => $role, 'content' => $content];
    }
}

$MAX_HISTORY_MESSAGES = 20;
if (count($history) > $MAX_HISTORY_MESSAGES) {
    $history = array_slice($history, -$MAX_HISTORY_MESSAGES);
}

// Validierung der Eingabe
if ($question === '') {
    echo json_encode(['error' => 'Keine Frage erhalten.']);
    exit;
}

// Prüfen, ob die API‑URL gesetzt ist
if (!isset($API_URL) || !$API_URL) {
    echo json_encode(['error' => 'API‑URL nicht konfiguriert.']);
    exit;
}

// Anfrage an die externe API senden
$messages = $history;
$messages[] = ['role' => 'user', 'content' => $question];
$payloadData = ['messages' => $messages];
if ($conversationId !== '') {
    $payloadData['conversation_id'] = $conversationId;
}
$payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

$answer = '';
$sources = [];
$errorResponse = null;

if ($payload === false) {
    $errorResponse = chatbot_build_user_error('API_MALFORMED_RESPONSE');
} else {
    $apiOptions = [
        'max_attempts' => 3,
        'connect_timeout' => 10,
        'timeout' => 30,
        'backoff_initial_ms' => 250,
        'backoff_factor' => 2.0,
    ];

    if (isset($API_ERROR_LOG) && is_string($API_ERROR_LOG) && $API_ERROR_LOG !== '') {
        $apiOptions['error_log_path'] = $API_ERROR_LOG;
    }

    $apiResult = chatbot_post_json_with_retry($API_URL, $payload, $apiOptions);

    if ($apiResult['success']) {
        $responseData = json_decode($apiResult['body'] ?? '', true);
        if (!is_array($responseData)) {
            if (!empty($apiOptions['error_log_path'])) {
                chatbot_append_api_error_log($apiOptions['error_log_path'], [
                    'url' => $API_URL,
                    'status_code' => $apiResult['status_code'] ?? null,
                    'error_code' => 'API_INVALID_RESPONSE',
                    'body_excerpt' => chatbot_truncate_for_log($apiResult['body'] ?? null),
                ]);
            }
            $errorResponse = chatbot_build_user_error('API_INVALID_RESPONSE');
        } elseif (isset($responseData['error'])) {
            $errorCode = isset($responseData['error_code']) ? (string)$responseData['error_code'] : 'API_REQUEST_FAILED';
            if (!empty($apiOptions['error_log_path'])) {
                chatbot_append_api_error_log($apiOptions['error_log_path'], [
                    'url' => $API_URL,
                    'status_code' => $apiResult['status_code'] ?? null,
                    'error_code' => $errorCode,
                    'body_excerpt' => chatbot_truncate_for_log($apiResult['body'] ?? null),
                ]);
            }
            $errorResponse = chatbot_build_user_error($errorCode);
        } elseif (!isset($responseData['answer'])) {
            if (!empty($apiOptions['error_log_path'])) {
                chatbot_append_api_error_log($apiOptions['error_log_path'], [
                    'url' => $API_URL,
                    'status_code' => $apiResult['status_code'] ?? null,
                    'error_code' => 'API_MALFORMED_RESPONSE',
                    'body_excerpt' => chatbot_truncate_for_log($apiResult['body'] ?? null),
                ]);
            }
            $errorResponse = chatbot_build_user_error('API_MALFORMED_RESPONSE');
        } else {
            $answer  = is_string($responseData['answer']) ? $responseData['answer'] : '';
            $sources = isset($responseData['sources']) && is_array($responseData['sources']) ? $responseData['sources'] : [];
            if (isset($responseData['conversation_id'])) {
                $cid = trim((string)$responseData['conversation_id']);
                if ($cid !== '') {
                    $cid = preg_replace('/[^a-zA-Z0-9_-]/', '', $cid);
                    $conversationId = substr($cid, 0, 64);
                }
            }
        }
    } else {
        $errorResponse = chatbot_build_user_error($apiResult['error_code'] ?? null);
    }
}

$answer = chatbot_sanitize_bot_answer($answer);

if ($conversationId === '') {
    try {
        $conversationId = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        $conversationId = 'conv_' . substr(hash('sha256', (string)microtime(true)), 0, 20);
    }
}

if ($errorResponse !== null) {
    echo json_encode([
        'error' => $errorResponse['message'],
        'error_code' => $errorResponse['code'],
        'support_hint' => $errorResponse['support_hint'],
        'sources' => [],
        'conversation_id' => $conversationId,
    ]);
    exit;
}

// Vollständigen Verlauf (inkl. aktueller Antwort) für Logging vorbereiten
$historyForLog = $messages;
$historyForLog[] = ['role' => 'assistant', 'content' => $answer];
$historyJson = json_encode($historyForLog, JSON_UNESCAPED_UNICODE);
if ($historyJson === false) {
    $historyJson = null;
}

// Log speichern, falls Datenbank vorhanden ist
if (isset($db) && $db instanceof PDO) {
    try {
        $stmt = $db->prepare('INSERT INTO logs (question, answer, conversation_id, history) VALUES (:question, :answer, :conversation_id, :history)');
        $stmt->execute([
            ':question' => $question,
            ':answer'   => $answer,
            ':conversation_id' => $conversationId,
            ':history'  => $historyJson,
        ]);
    } catch (Exception $e) {
        // Logging fehlgeschlagen – Fehler ignorieren, damit der Chat trotzdem funktioniert
    }
}

// Antwort ausgeben
echo json_encode([
    'answer'  => $answer,
    'sources' => $sources,
    'conversation_id' => $conversationId,
]);
