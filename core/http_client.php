<?php
/**
 * HTTP-Hilfsfunktionen für den Aufruf der externen Chat-API.
 */

declare(strict_types=1);

/**
 * Führt einen POST-Request mit JSON-Payload durch und liefert erweiterte Fehlerinformationen zurück.
 *
 * @param string $url
 * @param string $payload
 * @param array<string,mixed> $options
 * @return array{
 *   success:bool,
 *   body:?string,
 *   status_code:?int,
 *   attempts:int,
 *   error_code:?string,
 *   error_message:?string,
 *   curl_error:?string
 * }
 */
function chatbot_post_json_with_retry(string $url, string $payload, array $options = []): array
{
    $maxAttempts = isset($options['max_attempts']) ? max(1, (int)$options['max_attempts']) : 3;
    $connectTimeout = isset($options['connect_timeout']) ? max(1, (int)$options['connect_timeout']) : 10;
    $timeout = isset($options['timeout']) ? max(1, (int)$options['timeout']) : 30;
    $backoffInitialMs = isset($options['backoff_initial_ms']) ? max(50, (int)$options['backoff_initial_ms']) : 200;
    $backoffFactor = isset($options['backoff_factor']) ? max(1.0, (float)$options['backoff_factor']) : 2.0;
    $retryStatusCodes = isset($options['retry_status_codes']) && is_array($options['retry_status_codes'])
        ? array_map('intval', $options['retry_status_codes'])
        : [408, 425, 429, 500, 502, 503, 504];
    $errorLogPath = isset($options['error_log_path']) && is_string($options['error_log_path']) && $options['error_log_path'] !== ''
        ? $options['error_log_path']
        : null;

    $attempt = 0;
    $lastStatusCode = null;
    $lastBody = null;
    $lastErrorCode = null;
    $lastErrorMessage = null;
    $lastCurlError = null;

    while ($attempt < $maxAttempts) {
        $attempt++;

        $ch = curl_init($url);
        if ($ch === false) {
            $lastErrorCode = 'API_INIT_ERROR';
            $lastErrorMessage = 'cURL konnte nicht initialisiert werden.';
            break;
        }

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = $curlErrNo !== 0 ? curl_error($ch) : null;
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $lastStatusCode = $statusCode > 0 ? $statusCode : null;
        $lastBody = $response !== false ? (string)$response : null;
        $lastCurlError = $curlError;

        if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
            return [
                'success' => true,
                'body' => (string)$response,
                'status_code' => $statusCode,
                'attempts' => $attempt,
                'error_code' => null,
                'error_message' => null,
                'curl_error' => null,
            ];
        }

        $shouldRetry = false;
        if ($response === false) {
            if (in_array($curlErrNo, [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_RESOLVE_PROXY], true)) {
                $shouldRetry = true;
                $lastErrorCode = $curlErrNo === CURLE_OPERATION_TIMEDOUT ? 'API_TIMEOUT' : 'API_CONNECTION_ERROR';
            } else {
                $lastErrorCode = 'API_TRANSPORT_ERROR';
            }
            $lastErrorMessage = $curlError ?: 'Unbekannter Transportfehler';
        } else {
            $lastBody = (string)$response;
            if ($statusCode === 0) {
                $shouldRetry = true;
                $lastErrorCode = 'API_NO_STATUS';
                $lastErrorMessage = 'Kein HTTP-Statuscode erhalten.';
            } elseif (in_array($statusCode, $retryStatusCodes, true)) {
                $shouldRetry = true;
                $lastErrorCode = $statusCode >= 500 ? 'API_HTTP_5XX' : 'API_HTTP_' . $statusCode;
                $lastErrorMessage = 'HTTP-Status ' . $statusCode;
            } elseif ($statusCode >= 400) {
                $lastErrorCode = $statusCode >= 500 ? 'API_HTTP_5XX' : 'API_HTTP_' . $statusCode;
                $lastErrorMessage = 'HTTP-Status ' . $statusCode;
            } else {
                $lastErrorCode = 'API_UNEXPECTED_STATUS';
                $lastErrorMessage = 'Unerwarteter HTTP-Status ' . $statusCode;
            }
        }

        if (!$shouldRetry) {
            break;
        }

        if ($attempt < $maxAttempts) {
            $sleepMs = (int)round($backoffInitialMs * pow($backoffFactor, $attempt - 1));
            usleep($sleepMs * 1000);
        }
    }

    if ($errorLogPath !== null) {
        chatbot_append_api_error_log($errorLogPath, [
            'url' => $url,
            'status_code' => $lastStatusCode,
            'error_code' => $lastErrorCode,
            'error_message' => $lastErrorMessage,
            'body_excerpt' => chatbot_truncate_for_log($lastBody),
            'curl_error' => $lastCurlError,
            'attempts' => $attempt,
        ]);
    }

    return [
        'success' => false,
        'body' => $lastBody,
        'status_code' => $lastStatusCode,
        'attempts' => $attempt,
        'error_code' => $lastErrorCode,
        'error_message' => $lastErrorMessage,
        'curl_error' => $lastCurlError,
    ];
}

/**
 * Schreibt einen Logeintrag in das optionale Fehlerlog.
 *
 * @param string $path
 * @param array<string,mixed> $context
 * @return void
 */
function chatbot_append_api_error_log(string $path, array $context): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $entry = [
        'timestamp' => date('c'),
    ];

    foreach ($context as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $entry[$key] = $value;
    }

    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        $line = '[' . date('c') . '] ' . 'API error (json encoding failed)';
    }

    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Erstellt einen kompakten Ausschnitt für Logausgaben.
 *
 * @param string|null $body
 * @return string|null
 */
function chatbot_truncate_for_log(?string $body): ?string
{
    if ($body === null || $body === '') {
        return null;
    }

    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($body, 0, 500);
    } else {
        $excerpt = substr($body, 0, 500);
    }

    $excerpt = preg_replace('/\s+/u', ' ', $excerpt ?? '');
    if ($excerpt === null) {
        return null;
    }

    return trim($excerpt);
}
