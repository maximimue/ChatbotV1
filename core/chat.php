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

// Eingabedaten auslesen
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
$question = isset($inputData['question']) ? trim($inputData['question']) : '';

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
$payload = json_encode(['question' => $question]);
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
        $answer  = $responseData['answer'];
        $sources = $responseData['sources'] ?? [];
    } else {
        $answer  = 'Entschuldigung, keine gültige Antwort erhalten.';
        $sources = [];
    }
}

// Log speichern, falls Datenbank vorhanden ist
if (isset($db) && $db instanceof PDO) {
    try {
        $stmt = $db->prepare('INSERT INTO logs (question, answer) VALUES (:question, :answer)');
        $stmt->execute([
            ':question' => $question,
            ':answer'   => $answer,
        ]);
    } catch (Exception $e) {
        // Logging fehlgeschlagen – Fehler ignorieren, damit der Chat trotzdem funktioniert
    }
}

// Antwort ausgeben
echo json_encode([
    'answer'  => $answer,
    'sources' => $sources,
]);
