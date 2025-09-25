<?php
/**
 * /api/ask.php (HTML-Links Version)
 * Erzwingt, dass der Bot URLs im Antworttext als klickbare <a>-Tags ausgibt.
 * Usage: POST JSON { "question": "..." } to /api/ask.php?tenant=faehrhaus
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $ALLOWED_ORIGIN) {
    header('Access-Control-Allow-Origin: ' . $ALLOWED_ORIGIN);
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

require __DIR__ . '/helper.php';
require_once __DIR__ . '/../core/sanitizer.php';

$conversationId = '';

try {
    $tenant = $_GET['tenant'] ?? '';
    if (!$tenant) { throw new Exception('Missing tenant parameter.'); }

    $data = json_input();

    if (isset($data['conversation_id'])) {
        $cidCandidate = trim((string)$data['conversation_id']);
        if ($cidCandidate !== '') {
            $cidCandidate = preg_replace('/[^a-zA-Z0-9_-]/', '', $cidCandidate);
            $conversationId = substr($cidCandidate, 0, 64);
        }
    }

    $normalizeMessages = function (array $input, int $maxDepth) use ($MAX_QUESTION_CHARS): array {
        $result = [];
        foreach ($input as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = strtolower(trim((string)($item['role'] ?? '')));
            $content = trim((string)($item['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($role === 'bot') {
                $role = 'assistant';
            }
            if (!in_array($role, ['user', 'assistant', 'system'], true)) {
                continue;
            }
            if ($role === 'user' && $MAX_QUESTION_CHARS > 0 && mb_strlen($content, 'UTF-8') > $MAX_QUESTION_CHARS) {
                $content = mb_substr($content, 0, $MAX_QUESTION_CHARS, 'UTF-8');
            }
            $result[] = ['role' => $role, 'content' => $content];
        }
        if ($maxDepth > 0 && count($result) > $maxDepth) {
            $result = array_slice($result, -$maxDepth);
        }
        return $result;
    };

    $messages = [];
    if (isset($data['messages']) && is_array($data['messages'])) {
        $messages = $normalizeMessages($data['messages'], $MAX_HISTORY_DEPTH);
    } else {
        $questionLegacy = trim($data['question'] ?? '');
        if ($questionLegacy === '') { throw new Exception('Missing question.'); }
        if ($MAX_QUESTION_CHARS > 0 && mb_strlen($questionLegacy, 'UTF-8') > $MAX_QUESTION_CHARS) {
            $questionLegacy = mb_substr($questionLegacy, 0, $MAX_QUESTION_CHARS, 'UTF-8');
        }
        $historyLegacy = [];
        if (isset($data['history']) && is_array($data['history'])) {
            $historyLegacy = $normalizeMessages(
                $data['history'],
                $MAX_HISTORY_DEPTH > 0 ? max(0, $MAX_HISTORY_DEPTH - 1) : 0
            );
        }
        $messages = $historyLegacy;
        $messages[] = ['role' => 'user', 'content' => $questionLegacy];
    }

    if ($MAX_HISTORY_DEPTH > 0 && count($messages) > $MAX_HISTORY_DEPTH) {
        $messages = array_slice($messages, -$MAX_HISTORY_DEPTH);
    }

    $question = '';
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            $candidate = trim((string)$messages[$i]['content']);
            if ($candidate !== '') {
                if ($MAX_QUESTION_CHARS > 0 && mb_strlen($candidate, 'UTF-8') > $MAX_QUESTION_CHARS) {
                    $candidate = mb_substr($candidate, 0, $MAX_QUESTION_CHARS, 'UTF-8');
                    $messages[$i]['content'] = $candidate;
                }
                $question = $candidate;
                break;
            }
        }
    }
    if ($question === '') {
        throw new Exception('Missing question.');
    }

    $lastIndex = count($messages) - 1;
    if ($lastIndex < 0 || ($messages[$lastIndex]['role'] ?? '') !== 'user') {
        $messages[] = ['role' => 'user', 'content' => $question];
        if ($MAX_HISTORY_DEPTH > 0 && count($messages) > $MAX_HISTORY_DEPTH) {
            $messages = array_slice($messages, -$MAX_HISTORY_DEPTH);
        }
    }

    $hotel = load_hotel_config($tenant);
    $faqPath = $hotel['FAQ_FILE'] ?? null;
    $faqText = '';
    if ($faqPath && file_exists($faqPath)) {
        $faqText = file_get_contents($faqPath);
    }
    $context = $faqText ? pick_relevant_context($faqText, $question, min(8000, $MAX_CONTEXT_CHARS)) : '';

    $hotelName = $hotel['HOTEL_NAME'] ?? ucfirst($tenant);
    // Additional prompt tailoring from hotel configuration
    $promptExtra = $hotel['PROMPT_EXTRA'] ?? '';

    $sys = 'Du bist ein hilfreicher, präziser Hotel-Assistent. Antworte auf Deutsch. '
         . 'Nutze die bereitgestellten Hotelinformationen (FAQ) als primäre Quelle. '
         . 'Wenn Informationen fehlen oder unklar sind, sage ehrlich, dass du es nicht sicher weißt und verweise freundlich auf die Rezeption. Füge hierbei die E-Mail Adresse und auch die Telefonnummer hinzu, wenn du sie weißt.'
         . 'WICHTIG: Gib alle URLs in deinen Antworten IMMER als klickbare HTML-Links aus, z. B. <a href="https://beispiel.de">https://beispiel.de</a>. '
         . 'Verwende ausschließlich schlichte HTML-Links ohne zusätzliche Styles oder Klassen.';

    // Falls das Hotel eine eigene Erweiterung des System-Prompts definiert hat,
    // füge diese hier an. Dadurch kann sich jeder Standort individueller
    // präsentieren und gleichzeitig Verkaufsargumente dezent platzieren.
    if ($promptExtra) {
        $sys .= ' ' . trim($promptExtra);
    }

    $sys2 = 'Kontext – Hotel: ' . $hotelName . "\n\n" . ($context ?: '(kein spezieller Kontext gefunden)');

    $payloadMessages = [
        ['role' => 'system', 'content' => $sys],
        ['role' => 'system', 'content' => $sys2],
    ];
    foreach ($messages as $msg) {
        $payloadMessages[] = $msg;
    }

    $payload = [
        'model' => $OPENAI_MODEL,
        'temperature' => 0.3,
        'messages' => $payloadMessages,
    ];

    if (!$OPENAI_API_KEY || $OPENAI_API_KEY === 'PUT_YOUR_OPENAI_API_KEY_HERE') {
        throw new Exception('OpenAI API Key not configured. Set OPENAI_API_KEY.');
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        throw new Exception('OpenAI error: ' . ($err ?: $resp ?: ('HTTP ' . $code)));
    }
    $respData = json_decode($resp, true);
    $answer = $respData['choices'][0]['message']['content'] ?? null;
    if (!$answer) { throw new Exception('No answer from model.'); }

    $sources = [];
    if (!empty($hotel['HOTEL_URL'])) {
        $sources[] = ['title' => 'Hotel Website', 'url' => $hotel['HOTEL_URL']];
    }
    if ($faqPath) {
        $sources[] = ['title' => 'Hotel FAQ', 'url' => '/'.$tenant.'/faq'];
    }

    $answer = chatbot_sanitize_bot_answer($answer);

    echo json_encode([
        'answer'  => $answer, // enthält nun <a href="...">…</a>
        'sources' => $sources,
        'conversation_id' => $conversationId,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'answer'  => 'Entschuldigung, es gab ein technisches Problem (' . $e->getMessage() . ').',
        'sources' => [],
        'conversation_id' => $conversationId,
    ], JSON_UNESCAPED_UNICODE);
}
