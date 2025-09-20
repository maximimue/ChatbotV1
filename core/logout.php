<?php
/**
 * Beendet ausschließlich die Administrator-Session des aktuellen Hotels
 * und leitet anschließend zur Login-Seite zurück.
 */

$configPath = $configPath ?? null;

session_start();
require_once __DIR__ . '/init.php';

$adminSessionKey = $ADMIN_SESSION_KEY ?? 'admin';

if (isset($_SESSION[$adminSessionKey])) {
    unset($_SESSION[$adminSessionKey]);
}

// Session-Fixation vorbeugen
session_regenerate_id(true);

header('Location: login.php');
exit;

