<?php
/**
 * Editor für die FAQ‑Markdown‑Datei.
 *
 * Nur eingeloggte Administratoren dürfen diese Seite nutzen. Nach dem Speichern
 * werden die Änderungen sofort in die Datei geschrieben. Es gibt keine
 * Versionsverwaltung, daher empfiehlt sich eine regelmäßige Sicherung der Datei.
 */

session_start();
require_once __DIR__ . '/init.php';

$adminSessionKey = $ADMIN_SESSION_KEY ?? 'admin';
$sessionData = $_SESSION[$adminSessionKey] ?? null;
$isAuthenticated = is_array($sessionData)
    ? (!empty($sessionData['authenticated']))
    : ($sessionData === true);

if (!$isAuthenticated) {
    header('Location: login.php');
    exit;
}

header('Location: admin.php?tab=faq');
exit;