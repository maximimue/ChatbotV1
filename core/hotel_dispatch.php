<?php
/**
 * Gemeinsamer Einstiegspunkt für alle hotelspezifischen Wrapper-Dateien.
 *
 * Das Skript ermittelt anhand der aufgerufenen Datei, welche Core-Komponente
 * geladen werden soll, und stellt zuvor wiederkehrende Variablen bereit.
 */

$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? null;
if ($scriptFilename === null || !is_file($scriptFilename)) {
    $includedFiles = get_included_files();
    if (!empty($includedFiles)) {
        $scriptFilename = $includedFiles[0];
    }
}

if ($scriptFilename === null || !is_file($scriptFilename)) {
    http_response_code(500);
    echo 'Hotel-Dispatcher konnte die aufgerufene Datei nicht ermitteln.';
    exit;
}

$scriptBasename = basename($scriptFilename);
$hotelDirectory = dirname($scriptFilename);

$configPath = $hotelDirectory . '/config.php';
$coreRelative = '../core';

if ($scriptBasename === 'privacy.php' && !isset($chatReturnUrl)) {
    $chatReturnUrl = 'index.php';
}

$defaultHotelCss = 'assets/css/hotel.css';
if (!isset($hotelCssUrl) && is_file($hotelDirectory . '/' . $defaultHotelCss)) {
    $hotelCssUrl = $defaultHotelCss;
}

$scriptMap = [
    'admin.php'      => 'admin.php',
    'analysis.php'   => 'analysis.php',
    'chat.php'       => 'chat.php',
    'faq_editor.php' => 'faq_editor.php',
    'index.php'      => 'index.php',
    'login.php'      => 'login.php',
    'logout.php'     => 'logout.php',
    'privacy.php'    => 'privacy.php',
];

if (!isset($scriptMap[$scriptBasename])) {
    http_response_code(404);
    echo 'Unbekannte Seite.';
    exit;
}

require __DIR__ . '/' . $scriptMap[$scriptBasename];

