<?php
/**
 * Analyse-Seite für die protokollierten Benutzeranfragen (SQLite-Version).
 * Zeigt die am häufigsten gestellten Fragen, letzte Anfragen, Stoßzeiten etc.
 * Zugriff nur für Admins.
 */

session_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/analytics_helpers.php';

// Relativer Pfad zum Core-Verzeichnis (vom Hotel-Wrapper gesetzt)
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

// --- Debug optional aktivieren (?debug=1) ---
if (isset($_GET['debug'])) {
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    @error_reporting(E_ALL);
}

$analysisFilters = analytics_normalize_filters($_GET);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    analytics_export_csv($db ?? null, $analysisFilters);
}

$analysisData = analytics_fetch_data($db ?? null, $analysisFilters);
$queryString = analytics_query_string($analysisFilters);
$exportUrl = $_SERVER['PHP_SELF'] . '?' . ($queryString ? ($queryString . '&') : '') . 'export=csv';
$backLink = 'admin.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analyse der Anfragen</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
</head>
<body class="analytics-page">
<?php
$analysisFilters['tab'] = 'analysis';
include __DIR__ . '/partials/analysis_content.php';
?>
</body>
</html>