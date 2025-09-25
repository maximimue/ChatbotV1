<?php
// Dieses Konfigurationsbeispiel kann als Vorlage für jedes Hotel verwendet werden.
// Kopieren Sie diese Datei in jedes Hotelverzeichnis als config.php und passen Sie die Werte an.

// Die Basis-URL der Python‑API, die die Chat‑Anfragen beantwortet. Beispiel: http://localhost:8000/ask
$API_URL = 'http://localhost:8000/ask';

// Name des Hotels oder der Site. Erscheint im Titel und als Fallback‑Überschrift.
$HOTEL_NAME = 'Hotel Name';

// Pfad zum Hotellogo. Für die meisten Konfigurationen relativ zum Hotelverzeichnis, z. B. __DIR__.'/logo.png'.
$LOGO_PATH = __DIR__ . '/logo.png';

// Optional: Eigene Bezeichnung für den Bot (erscheint in den Chatblasen).
$BOT_NAME = 'Max';

// Optional: Relativer Pfad zu einer zusätzlichen CSS-Datei für individuelle Styles.
$HOTEL_CSS_URL = 'assets/css/hotel.css';

// Optional: Bild für den Seitenhintergrund. Relativ zum Hotelordner oder als URL.
$BACKGROUND_IMAGE_URL = '';

// Farbwerte für das Standard-Layout. Alle abgeleiteten Farben orientieren sich an diesen fünf Tokens.
$THEME_COLOR_BASE = '#F0F0F0';
$THEME_COLOR_SURFACE = '#FFFFFF';
$THEME_COLOR_PRIMARY = '#003366';
$THEME_COLOR_PRIMARY_CONTRAST = '#FFFFFF';
$THEME_COLOR_TEXT = '#0F172A';

// Pfad zur FAQ‑Markdown‑Datei, die die Wissensbasis für das Hotel enthält.
$FAQ_FILE = __DIR__ . '/data/faq.md';

// Pfad zur SQLite‑Datenbank, in der Benutzerfragen und Antworten protokolliert werden.
$LOG_DB = __DIR__ . '/data/logs.sqlite';

// Optional: Datei für detaillierte API-Fehlerlogs (muss beschreibbar sein).
// $API_ERROR_LOG = __DIR__ . '/data/api_errors.log';

// URL der Haupt‑Hotel‑Website. Diese wird für den "Zurück zur Website"‑Link verwendet.
$HOTEL_URL = 'https://www.example-hotel.de';

// Administrativer Benutzername. Für den Zugriff auf das Backend (FAQ/Analyse/Einstellungen).
$ADMIN_USER = 'admin';

// Passwort‑Hash für den administrativen Zugang. Verwenden Sie password_hash() zur Generierung eines sicheren Hashes.
$ADMIN_PASSWORD_HASH = password_hash('changeme', PASSWORD_DEFAULT);

// Alternativ können mehrere Benutzer über $ADMIN_USERS hinterlegt werden (Benutzername + Passwort-Hash).
// Die Administrationsoberfläche legt ansonsten eine data/admin_users.json an und verwaltet diese Datei.
// $ADMIN_USERS = [
//     [
//         'username' => 'admin',
//         'password_hash' => password_hash('changeme', PASSWORD_DEFAULT),
//     ],
// ];

// Hinweis: Diese Datei ist nur ein Beispiel. Für jedes Hotel sollten Sie eine eigene config.php mit angepassten Werten anlegen.

// Optional: Zusätzliche Anweisungen für das System-Prompt des Chatbots.
$PROMPT_EXTRA = '';

// Optional: Eindeutiger Schlüssel für das Hotel (z. B. für Session-Namespace).
// Wird er nicht gesetzt, nutzt das System automatisch den Namen des Hotelordners.
// $HOTEL_KEY = 'mein-hotel';
