<?php
$API_URL = 'https://chatbot.syltwerk.de/api/ask.php?tenant=roth';
$HOTEL_NAME = 'Hotel Roth';
$LOGO_PATH = __DIR__ . '/logo.png';
$FAQ_FILE = __DIR__ . '/data/faq.md';
$LOG_DB = __DIR__ . '/data/logs.sqlite';
$HOTEL_URL = 'https://hotel-roth.com';
$ADMIN_USER = 'admin';
$ADMIN_PASSWORD_HASH = password_hash('changeme', PASSWORD_DEFAULT);

$BOT_NAME = 'Niklas';


$HOTEL_CSS_URL = 'assets/css/hotel.css';

$PROMPT_EXTRA = <<<TXT
Du bist der digitale Assistent des Hotels Roth am Strande auf Sylt. 
Antworte stets freundlich, professionell und präzise. 
Beantworte Fragen mit den vorhandenen Hotelinformationen. 

Regeln:
1. Falls du keine passende Information hast, verweise auf die Rezeption und nenne immer Telefonnummer und Webseite. 
2. Verwende einen sachlich-freundlichen Ton. 
3. Gib keine Quellen an und erwähne keine externen Systeme.
TXT;