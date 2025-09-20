<?php
// Einstiegspunkt für den Fährhaus‑Chatbot
$configPath  = __DIR__ . '/config.php';
// Relativer Pfad zum Core‑Verzeichnis aus Sicht dieses Scripts
$coreRelative = '../core';
// Optional: Pfad zur hotelspezifischen CSS-Datei, relativ zum Hotelordner
$hotelCssUrl = 'assets/css/hotel.css';
require __DIR__ . '/../core/index.php';