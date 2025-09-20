<?php
// Small helpers used by ask.php

function json_input() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = []; }
    return $data;
}

/**
 * Load a hotel's config.php (e.g., /httpdocs/faehrhaus/config.php) safely.
 * Returns an associative array with useful fields.
 */
function load_hotel_config($tenant) {
    $baseDir = realpath(__DIR__ . '/..'); // /httpdocs
    $cfgFile = $baseDir . '/' . $tenant . '/config.php';
    if (!preg_match('/^[a-z0-9_-]+$/i', $tenant)) {
        throw new Exception('Invalid tenant.');
    }
    if (!file_exists($cfgFile)) {
        throw new Exception('Hotel config not found for tenant: ' . $tenant);
    }
    // Isolate scope to avoid leaking variables
    $HOTEL_NAME = null; $HOTEL_URL = null; $FAQ_FILE = null;
    $LOG_DB = null; $API_URL = null; $LOGO_PATH = null;
    // Additional optional variables for hotel-specific overrides
    $HOTEL_CSS_URL = null; // URL to a hotel-specific CSS file (relative to hotel root)
    $PROMPT_EXTRA = null;  // Additional prompt instructions to customise the system message
    require $cfgFile; // expects the vars to be set inside

    return [
        'HOTEL_NAME'     => $HOTEL_NAME ?? ucfirst($tenant),
        'HOTEL_URL'      => $HOTEL_URL ?? null,
        'FAQ_FILE'       => $FAQ_FILE ?? null,
        'LOG_DB'         => $LOG_DB ?? null,
        'LOGO_PATH'      => $LOGO_PATH ?? null,
        'API_URL'        => $API_URL ?? null,
        // New fields for custom styling and prompt tuning
        'HOTEL_CSS_URL'  => $HOTEL_CSS_URL ?? null,
        'PROMPT_EXTRA'   => $PROMPT_EXTRA ?? null,
    ];
}

/**
 * Very small relevance picker: split FAQ into paragraphs and score by word overlap.
 * Returns a string with the best ~5 paragraphs limited by $maxChars.
 */
function pick_relevant_context($faqText, $question, $maxChars = 8000) {
    $question = mb_strtolower($question, 'UTF-8');
    $qTokens = preg_split('/[^\p{L}\p{Nd}]+/u', $question, -1, PREG_SPLIT_NO_EMPTY);
    $qSet = array_unique(array_filter($qTokens, function($t){ return mb_strlen($t, 'UTF-8') > 2; }));

    $paras = preg_split('/\n{2,}/', $faqText); // paragraphs / sections
    $scored = [];
    foreach ($paras as $p) {
        $pLower = mb_strtolower($p, 'UTF-8');
        $score = 0;
        foreach ($qSet as $t) {
            if (mb_strpos($pLower, $t) !== false) { $score++; }
        }
        if ($score > 0) {
            $scored[] = ['score' => $score, 'text' => trim($p)];
        }
    }
    if (empty($scored)) {
        // fallback: first ~10 paragraphs
        $scored = array_map(function($p){ return ['score'=>1, 'text'=>trim($p)]; }, array_slice($paras, 0, 10));
    } else {
        usort($scored, function($a,$b){ return $b['score'] <=> $a['score']; });
    }
    $ctx = "";
    foreach ($scored as $item) {
        $next = $item['text'] . "\n\n";
        if (mb_strlen($ctx . $next, 'UTF-8') > $maxChars) break;
        $ctx .= $next;
    }
    return trim($ctx);
}
