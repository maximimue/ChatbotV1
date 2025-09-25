<?php
/**
 * Gemeinsame Initialisierungsroutine für den Chatbot.
 *
 * Dieses Skript lädt die hotelspezifische Konfiguration, startet eine Session
 * und stellt bei Bedarf eine Verbindung zur SQLite‑Datenbank für das Logging her.
 *
 * Vor der Einbindung dieses Skripts muss die Variable $configPath definiert sein,
 * welche auf die config.php des jeweiligen Hotels verweist. Wird die Variable
 * nicht gesetzt, versucht init.php eine config.php im übergeordneten Verzeichnis
 * zu laden. Dies ermöglicht Tests im core‑Verzeichnis.
 */

// Session für Login und Tracking starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ermitteln und Laden der Konfiguration
if (isset($configPath) && file_exists($configPath)) {
    $resolvedConfig = realpath($configPath);
    require $configPath;
} elseif (file_exists(__DIR__ . '/../config.php')) {
    // Fallback für Entwicklungszwecke: lokale config.php im Projekt
    $resolvedConfig = realpath(__DIR__ . '/../config.php');
    require __DIR__ . '/../config.php';
} else {
    // Keine Konfiguration gefunden => Fehlermeldung ausgeben
    die('Konfigurationsdatei nicht gefunden. Bitte setzen Sie $configPath vor dem Einbinden von init.php.');
}

if (!function_exists('chatbot_normalize_hex_color')) {
    /**
     * Normalisiert Hex-Farbwerte (#RRGGBB) und gibt sie in konsistenter Großschreibung zurück.
     *
     * @param string|null $value
     * @return string|null
     */
    function chatbot_normalize_hex_color(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
            return null;
        }

        $hex = strtoupper($matches[1]);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0]
                . $hex[1] . $hex[1]
                . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }
}

$chatbotThemeDefaults = [
    'THEME_COLOR_BASE'              => '#F0F0F0',
    'THEME_COLOR_SURFACE'           => '#FFFFFF',
    'THEME_COLOR_PRIMARY'           => '#003366',
    'THEME_COLOR_PRIMARY_CONTRAST'  => '#FFFFFF',
    'THEME_COLOR_TEXT'              => '#0F172A',
];

foreach ($chatbotThemeDefaults as $themeKey => $defaultHex) {
    $explicit = isset(${$themeKey}) ? chatbot_normalize_hex_color((string)${$themeKey}) : null;

    if ($explicit === null) {
        $explicit = $defaultHex;
    }

    ${$themeKey} = $explicit;
}

// Basisverzeichnis des Hotels und Pfad zur Konfiguration verfügbar machen
if (!isset($HOTEL_BASE_PATH) || !is_string($HOTEL_BASE_PATH) || $HOTEL_BASE_PATH === '') {
    if (isset($resolvedConfig) && $resolvedConfig !== false) {
        $HOTEL_BASE_PATH = dirname($resolvedConfig);
    } else {
        $HOTEL_BASE_PATH = dirname(__DIR__);
    }
}

if (!isset($HOTEL_CONFIG_PATH) || !is_string($HOTEL_CONFIG_PATH) || $HOTEL_CONFIG_PATH === '') {
    $HOTEL_CONFIG_PATH = isset($resolvedConfig) && $resolvedConfig !== false ? $resolvedConfig : null;
}

// Eindeutigen Schlüssel für das Hotel ableiten (Konfiguration kann optional $HOTEL_KEY setzen)
if (!isset($HOTEL_KEY) || !is_string($HOTEL_KEY) || $HOTEL_KEY === '') {
    if (isset($resolvedConfig) && $resolvedConfig !== false) {
        $HOTEL_KEY = basename(dirname($resolvedConfig));
    } else {
        $HOTEL_KEY = 'default';
    }
}

// Session-Namespace für die Admin-Authentifizierung vorbereiten
if (!isset($ADMIN_SESSION_KEY) || !is_string($ADMIN_SESSION_KEY) || $ADMIN_SESSION_KEY === '') {
    $ADMIN_SESSION_KEY = 'admin_auth_' . hash('sha256', $HOTEL_KEY);
}

// Einrichtung der SQLite‑Datenbank für die Protokollierung
if (isset($LOG_DB) && is_string($LOG_DB)) {
    try {
        // Datenbankverbindung herstellen
        $db = new PDO('sqlite:' . $LOG_DB);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Tabelle für Logs erstellen, falls sie noch nicht existiert
        $db->exec('CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            conversation_id TEXT,
            history TEXT,
            time DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        try {
            $db->exec('ALTER TABLE logs ADD COLUMN conversation_id TEXT');
        } catch (Exception $e) {
            // Spalte existiert bereits
        }
        try {
            $db->exec('ALTER TABLE logs ADD COLUMN history TEXT');
        } catch (Exception $e) {
            // Spalte existiert bereits
        }
        $db->exec('CREATE TABLE IF NOT EXISTS health_checks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            status_code INTEGER,
            latency_ms REAL,
            success INTEGER NOT NULL,
            error_code TEXT,
            response_excerpt TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    } catch (Exception $e) {
        // Bei Fehlern die Verbindung null setzen
        $db = null;
    }
} else {
    // Kein Logging, wenn keine Datenbank definiert wurde
    $db = null;
}

if (!function_exists('chatbot_asset_url')) {
    /**
     * Ermittelt eine auslieferbare URL für konfigurierbare Assets.
     *
     * Unterstützt absolute Dateipfade, relative Pfade (bezogen auf den Hotelordner)
     * sowie vollständige URLs.
     *
     * @param string|null $value
     * @param string|null $hotelBasePath
     * @return string|null
     */
    function chatbot_asset_url(?string $value, ?string $hotelBasePath = null): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $lower = strtolower($value);
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) || strpos($value, '//') === 0 || strpos($lower, 'data:') === 0) {
            return $value;
        }

        if ($value[0] === '/') {
            if ($hotelBasePath) {
                $baseReal = realpath($hotelBasePath) ?: $hotelBasePath;
                $normalizedBase = str_replace('\\', '/', rtrim($baseReal, '/\\')) . '/';

                $valueReal = realpath($value);
                $normalizedAbsolute = $valueReal !== false
                    ? str_replace('\\', '/', $valueReal)
                    : str_replace('\\', '/', $value);

                if (strpos($normalizedAbsolute, $normalizedBase) === 0) {
                    $relative = substr($normalizedAbsolute, strlen($normalizedBase));
                    $relative = ltrim($relative, '/');
                    return $relative === '' ? basename($normalizedAbsolute) : $relative;
                }
            }

            return $value;
        }

        $normalizedValue = str_replace('\\', '/', $value);

        if ($hotelBasePath) {
            $normalizedBase = str_replace('\\', '/', rtrim($hotelBasePath, '/\\')) . '/';

            if (strpos($normalizedValue, $normalizedBase) === 0) {
                $relative = substr($normalizedValue, strlen($normalizedBase));
                return $relative === '' ? basename($normalizedValue) : $relative;
            }

            $combined = $normalizedBase . ltrim($normalizedValue, '/');
            if (file_exists($combined)) {
                $relative = ltrim(str_replace('\\', '/', $normalizedValue), '/');
                return $relative === '' ? basename($normalizedValue) : $relative;
            }
        }

        if (file_exists($value)) {
            $normalizedFull = str_replace('\\', '/', $value);
            if ($hotelBasePath) {
                $normalizedBase = str_replace('\\', '/', rtrim($hotelBasePath, '/\\')) . '/';
                if (strpos($normalizedFull, $normalizedBase) === 0) {
                    $relative = substr($normalizedFull, strlen($normalizedBase));
                    return $relative === '' ? basename($normalizedFull) : $relative;
                }
            }
            return basename($normalizedFull);
        }

        return $value;
    }
}
