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
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n" .
                    'Content-Length: ' . strlen($payload) . "\r\n",
        'content' => $payload,
        'timeout' => 30,
    ],
];
$context = stream_context_create($options);
$apiResponse = @file_get_contents($API_URL, false, $context);

// Fehlerbehandlung bei der API‑Abfrage
if ($apiResponse === false) {
    $answer  = 'Entschuldigung, es gab ein Problem beim Abrufen der Antwort.';
    $sources = [];
} else {
    $responseData = json_decode($apiResponse, true);
    if (is_array($responseData) && isset($responseData['answer'])) {
        $answer  = is_string($responseData['answer']) ? $responseData['answer'] : '';
        $sources = $responseData['sources'] ?? [];
        if (isset($responseData['conversation_id'])) {
            $cid = trim((string)$responseData['conversation_id']);
            if ($cid !== '') {
                $cid = preg_replace('/[^a-zA-Z0-9_-]/', '', $cid);
                $conversationId = substr($cid, 0, 64);
            }
        }
    } else {
        $answer  = 'Entschuldigung, keine gültige Antwort erhalten.';
        $sources = [];
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
