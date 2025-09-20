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

// Relativer Pfad zum Core‑Verzeichnis (vom Hotel‑Wrapper gesetzt)
$coreRelative = $coreRelative ?? '.';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$currentContent = '';

// FAQ‑Datei laden, falls vorhanden
if (isset($FAQ_FILE) && file_exists($FAQ_FILE)) {
    $currentContent = file_get_contents($FAQ_FILE);
}

// Bei POST speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newContent = $_POST['content'] ?? '';
    if ($FAQ_FILE) {
        file_put_contents($FAQ_FILE, $newContent);
        $message = 'FAQ wurde erfolgreich gespeichert.';
        $currentContent = $newContent;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>FAQ bearbeiten</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
    <style>
    body {
        padding: 20px;
        font-family: Arial, Helvetica, sans-serif;
    }
    textarea {
        width: 100%;
        height: 60vh;
        font-family: monospace;
        padding: 10px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    button {
        margin-top: 10px;
        padding: 8px 12px;
        background: #003366;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    button:hover {
        background: #004080;
    }
    .message {
        color: green;
        margin-bottom: 10px;
    }
    </style>
</head>
<body>
    <h1>FAQ bearbeiten – <?php echo htmlspecialchars($HOTEL_NAME); ?></h1>
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <textarea name="content"><?php echo htmlspecialchars($currentContent); ?></textarea>
        <br>
        <button type="submit">Speichern</button>
    </form>
    <p><a href="admin.php">Zurück zum Admin Bereich</a></p>
</body>
</html>