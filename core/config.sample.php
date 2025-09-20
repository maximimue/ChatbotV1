<?php
// Dieses Konfigurationsbeispiel kann als Vorlage für jedes Hotel verwendet werden.
// Kopieren Sie diese Datei in jedes Hotelverzeichnis als config.php und passen Sie die Werte an.

// Die Basis-URL der Python‑API, die die Chat‑Anfragen beantwortet. Beispiel: http://localhost:8000/ask
$API_URL = 'http://localhost:8000/ask';

// Name des Hotels oder der Site. Erscheint im Titel und als Fallback‑Überschrift.
$HOTEL_NAME = 'Hotel Name';

// Pfad zum Hotellogo. Für die meisten Konfigurationen relativ zum Hotelverzeichnis, z. B. __DIR__.'/logo.png'.
$LOGO_PATH = __DIR__ . '/logo.png';

// Pfad zur FAQ‑Markdown‑Datei, die die Wissensbasis für das Hotel enthält.
$FAQ_FILE = __DIR__ . '/data/faq.md';

// Pfad zur SQLite‑Datenbank, in der Benutzerfragen und Antworten protokolliert werden.
$LOG_DB = __DIR__ . '/data/logs.sqlite';

// URL der Haupt‑Hotel‑Website. Diese wird für den "Zurück zur Website"‑Link verwendet.
$HOTEL_URL = 'https://www.example-hotel.de';

// Administrativer Benutzername. Für den Zugriff auf das Backend (FAQ‑Editor/Analyse).
$ADMIN_USER = 'admin';

// Passwort‑Hash für den administrativen Zugang. Verwenden Sie password_hash() zur Generierung eines sicheren Hashes.
$ADMIN_PASSWORD_HASH = password_hash('changeme', PASSWORD_DEFAULT);

// Hinweis: Diese Datei ist nur ein Beispiel. Für jedes Hotel sollten Sie eine eigene config.php mit angepassten Werten anlegen.

// Optional: Eindeutiger Schlüssel für das Hotel (z. B. für Session-Namespace).
// Wird er nicht gesetzt, nutzt das System automatisch den Namen des Hotelordners.
// $HOTEL_KEY = 'mein-hotel';
