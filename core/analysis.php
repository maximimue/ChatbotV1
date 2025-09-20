<?php
/**
 * Analyse-Seite für die protokollierten Benutzeranfragen.
 *
 * Zeigt die am häufigsten gestellten Fragen sowie eine Liste der letzten
 * Anfragen mit Antworten. Der Zugriff ist Administratoren vorbehalten.
 */

session_start();
require_once __DIR__ . '/init.php';

// Relativer Pfad zum Core‑Verzeichnis (vom Hotel‑Wrapper gesetzt)
$coreRelative = $coreRelative ?? '.';

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Daten vorbereiten
$stats = [];
$logs  = [];
if (isset($db) && $db instanceof PDO) {
    // Top 20 Fragen nach Häufigkeit
    $stmt = $db->query('SELECT question, COUNT(*) AS cnt FROM logs GROUP BY question ORDER BY cnt DESC LIMIT 20');
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Letzte 100 Einträge
    $stmt2 = $db->query('SELECT question, answer, time FROM logs ORDER BY time DESC LIMIT 100');
    $logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Analyse der Anfragen</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
    <style>
    body {
        padding: 20px;
        font-family: Arial, Helvetica, sans-serif;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 6px;
        text-align: left;
    }
    th {
        background: #f0f0f0;
    }
    </style>
</head>
<body>
    <h1>Analyse – <?php echo htmlspecialchars($HOTEL_NAME); ?></h1>
    <h2>Häufig gestellte Fragen (Top 20)</h2>
    <table>
        <tr><th>Frage</th><th>Anzahl</th></tr>
        <?php foreach ($stats as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['question']); ?></td>
            <td><?php echo htmlspecialchars($row['cnt']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <h2>Letzte 100 Anfragen</h2>
    <table>
        <tr><th>Zeit</th><th>Frage</th><th>Antwort</th></tr>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['time']); ?></td>
            <td><?php echo htmlspecialchars($row['question']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($row['answer'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a href="admin.php">Zurück zum Admin Bereich</a></p>
</body>
</html>