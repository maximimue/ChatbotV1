<?php
// Konfiguration für das Hotel Aarnhoog – aktualisiert für die PHP-API
$API_URL = 'https://chatbot.syltwerk.de/api/ask.php?tenant=aarnhoog';
$HOTEL_NAME = 'Aarnhoog';
$LOGO_PATH = __DIR__ . '/logo.png';
$FAQ_FILE = __DIR__ . '/data/faq.md';
$LOG_DB = __DIR__ . '/data/logs.sqlite';
$HOTEL_URL = 'https://aarnhoog.de';
$ADMIN_USER = 'admin';
$ADMIN_PASSWORD_HASH = password_hash('changeme', PASSWORD_DEFAULT);

$BOT_NAME = 'Kristan';

// Pfad zur hotelspezifischen CSS-Datei relativ zum Hotelverzeichnis.
// Diese Datei enthält Anpassungen für Farben, Schriftarten und weitere Stilelemente.
$HOTEL_CSS_URL = 'assets/css/hotel.css';

// Zusätzliche Anweisungen für die Systemmeldungen des Chatbots. Der Text soll
// die Besonderheiten des Hotels hervorheben und Gäste freundlich zur
// Direktbuchung animieren, ohne aufdringlich zu wirken.
$PROMPT_EXTRA = <<<TXT
Sprich im Ton des Aarnhoog: nordisch-ruhig, persönlich, zurückhaltend elegant.  
Betone: Boutique-Charakter, Reetdach-Charme in Keitum, wenige Suiten/Zimmer, viel Privatsphäre, kleiner feiner Spa/Pool, großzügiges Frühstück, Nähe zum Wattenmeer.  
Antworte präzise und lösungsorientiert. Biete unaufdringlich an: „unverbindlich anfragen“ oder „jetzt direkt buchen“, wenn es passt. 
Wenn Informationen nicht vorliegen, nenne **niemals**, dass diese nicht eingespielt oder nicht verfügbar sind.  
Verweise stattdessen direkt auf die Rezeption mit klarer Kontaktmöglichkeit:  
- Telefon: 04651/3990  
- E-Mail: <a href="mailto:info@aarnhoog.de">info@aarnhoog.de</a>  
Keine Übertreibungen, keine Floskeln. Hilf Gästen, schnell die richtige Entscheidung zu treffen.

TXT;