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
            time DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    } catch (Exception $e) {
        // Bei Fehlern die Verbindung null setzen
        $db = null;
    }
} else {
    // Kein Logging, wenn keine Datenbank definiert wurde
    $db = null;
}