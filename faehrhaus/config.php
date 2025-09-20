<?php
// Konfiguration für das Hotel Fährhaus – aktualisiert für die PHP-API
$API_URL = 'https://chatbot.syltwerk.de/api/ask.php?tenant=faehrhaus';
$HOTEL_NAME = 'Fährhaus';
$LOGO_PATH = __DIR__ . '/logo.png';
$FAQ_FILE = __DIR__ . '/data/faq.md';
$LOG_DB = __DIR__ . '/data/logs.sqlite';
$HOTEL_URL = 'https://faehrhaus-sylt.de';
$ADMIN_USER = 'admin';
$ADMIN_PASSWORD_HASH = password_hash('changeme', PASSWORD_DEFAULT);

$BOT_NAME = 'Max';

// Pfad zur hotelspezifischen CSS-Datei relativ zum Hotelverzeichnis.
$HOTEL_CSS_URL = 'assets/css/hotel.css';

// Zusätzliche Anweisungen für die Systemmeldungen des Chatbots. Der Text
// betont die Vorzüge des Fährhaus Sylt, inspiriert Gäste und regt zur
// Direktbuchung an, ohne zu aufdringlich zu sein.
$PROMPT_EXTRA = <<<TXT
Sprich im Ton des FÄHRHAUS SYLT: hanseatisch-elegant, unaufgeregt, verlässlich.
Bevorzuge präzise, kurze Antworten mit klaren nächsten Schritten. Betone:
– Lage direkt am Wattenmeer (Munkmarsch), ruhige Exklusivität
– Kulinarik (Restaurant Fährhaus, Selmer’s), feiner Frühstücks-/Abendservice
– Spa & Wellness (ruhige Auszeit), dezenter Luxus, persönliche Betreuung
– Zimmer & Suiten: skandinavische Klassik trifft friesische Gemütlichkeit
Biete bei Anfragen subtil eine „unverbindliche Anfrage“ oder „Online buchen“ an,
ohne Druck. Nenne Kontakt (04651/93970, info@faehrhaus-sylt.de) nur, wenn hilfreich.
Keine übertriebene Werbung; sachlich, lösungsorientiert, gastfreundlich.
TXT;