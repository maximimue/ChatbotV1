<?php
/**
 * Übersichtsseite für den Administrationsbereich.
 *
 * Hier werden Links zum FAQ‑Editor, zur Analyse und zum Logout angeboten.
 */

session_start();
require_once __DIR__ . '/init.php';

// Relativer Pfad zum Core‑Verzeichnis (vom Hotel‑Wrapper gesetzt)
$coreRelative = $coreRelative ?? '.';

$adminSessionKey = $ADMIN_SESSION_KEY ?? 'admin';
$sessionData = $_SESSION[$adminSessionKey] ?? null;
$isAuthenticated = is_array($sessionData)
    ? (!empty($sessionData['authenticated']))
    : ($sessionData === true);

if (!$isAuthenticated) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin Bereich</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
    <style>
    body {
        padding: 20px;
        font-family: Arial, Helvetica, sans-serif;
    }
    ul { list-style: none; padding: 0; }
    li { margin-bottom: 10px; }
    a.button {
        display: inline-block;
        padding: 8px 12px;
        background: #003366;
        color: #fff;
        border-radius: 4px;
        text-decoration: none;
    }
    a.button:hover {
        background: #004080;
    }
    </style>
</head>
<body>
    <h1>Admin Bereich – <?php echo htmlspecialchars($HOTEL_NAME); ?></h1>
    <ul>
        <li><a class="button" href="faq_editor.php">FAQ bearbeiten</a></li>
        <li><a class="button" href="analysis.php">Anfragen analysieren</a></li>
        <li><a class="button" href="logout.php">Logout</a></li>
    </ul>
</body>
</html>