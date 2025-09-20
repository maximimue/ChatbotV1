<?php
/**
 * Analyse-Seite für die protokollierten Benutzeranfragen (SQLite-Version).
 * Zeigt die am häufigsten gestellten Fragen, letzte Anfragen, Stoßzeiten etc.
 * Zugriff nur für Admins.
 */

session_start();
require_once __DIR__ . '/init.php';

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

// --- Daten und Filter vorbereiten ---
$stats = [];
$logs  = [];
$byHour = [];
$byDay  = [];
$byDow  = [];
$totals = ['total'=>0,'empty'=>0,'avg_q_len'=>0,'avg_a_len'=>0];
$fatalError = null;

// Filter
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end   = isset($_GET['end'])   ? trim($_GET['end'])   : '';
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit']  : 100;
if ($limit < 10 || $limit > 1000) { $limit = 100; }

// WHERE-Klausel aufbauen (SQLite)
$where = [];
$params = [];
if ($start !== '') { $where[] = "time >= :start"; $params[':start'] = $start . ' 00:00:00'; }
if ($end   !== '') { $where[] = "time <= :end";   $params[':end']   = $end   . ' 23:59:59'; }
if ($q     !== '') { $where[] = "question LIKE :q"; $params[':q'] = '%' . $q . '%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// CSV-Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && isset($db) && $db instanceof PDO) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="chat_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['time','question','answer']);
    $sqlExport = "SELECT time, question, answer FROM logs $whereSql ORDER BY time DESC LIMIT 10000";
    $stmtE = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmtE->bindValue($k, $v); }
    $stmtE->execute();
    while ($row = $stmtE->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['time'], $row['question'], $row['answer']]);
    }
    fclose($out);
    exit;
}

// Export-URL mit aktiven Parametern
$queryParams = http_build_query(array_filter([
    'start' => $start,
    'end'   => $end,
    'q'     => $q,
    'limit' => $limit
], function($v){ return $v !== null && $v !== ''; }));
$exportUrl = $_SERVER['PHP_SELF'] . '?' . ($queryParams ? ($queryParams . '&') : '') . 'export=csv';

// --- SQLite-Funktionsalias / Formatierung ---
// time: TEXT/DATETIME kompatibel
$fnDate = "strftime('%Y-%m-%d', time)";               // Tagesaggregation
$fnHour = "CAST(strftime('%H', time) AS INTEGER)";     // Stunde 0..23
$fnDow  = "CAST(strftime('%w', time) AS INTEGER)";     // 0=So..6=Sa
$fnQLen = "LENGTH(question)";
$fnALen = "LENGTH(answer)";
$dowIsSundayZero = true; // für die Anzeige

// --- Datenbankabfragen ---
if (isset($db) && $db instanceof PDO) {
  try {
    // Top 20 Fragen
    $sqlTop = "SELECT question, COUNT(*) AS cnt
               FROM logs
               $whereSql
               GROUP BY question
               ORDER BY cnt DESC
               LIMIT 20";
    $stmtTop = $db->prepare($sqlTop);
    foreach ($params as $k => $v) { $stmtTop->bindValue($k, $v); }
    $stmtTop->execute();
    $stats = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    // Letzte Einträge
    $sqlLast = "SELECT question, answer, time
                FROM logs
                $whereSql
                ORDER BY time DESC
                LIMIT :limit";
    $stmtLast = $db->prepare($sqlLast);
    foreach ($params as $k => $v) { $stmtLast->bindValue($k, $v); }
    $stmtLast->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtLast->execute();
    $logs = $stmtLast->fetchAll(PDO::FETCH_ASSOC);

    // KPIs / Totals
    $sqlTotals = "SELECT COUNT(*) AS total,
                         SUM(CASE WHEN (answer IS NULL OR answer='') THEN 1 ELSE 0 END) AS empty_cnt,
                         AVG($fnQLen) AS avg_q_len,
                         AVG($fnALen) AS avg_a_len
                  FROM logs
                  $whereSql";
    $stmtT = $db->prepare($sqlTotals);
    foreach ($params as $k => $v) { $stmtT->bindValue($k, $v); }
    $stmtT->execute();
    $t = $stmtT->fetch(PDO::FETCH_ASSOC);
    if ($t) {
        $totals['total']     = (int)$t['total'];
        $totals['empty']     = (int)$t['empty_cnt'];
        $totals['avg_q_len'] = (int)round($t['avg_q_len']);
        $totals['avg_a_len'] = (int)round($t['avg_a_len']);
    }

    // Stoßzeiten (Stunde)
    $sqlHour = "SELECT $fnHour AS h, COUNT(*) AS cnt
                FROM logs
                $whereSql
                GROUP BY $fnHour
                ORDER BY h";
    $stmtH = $db->prepare($sqlHour);
    foreach ($params as $k => $v) { $stmtH->bindValue($k, $v); }
    $stmtH->execute();
    $byHour = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    // Verlauf je Tag (letzte 30 Tage)
    $sqlDay = "SELECT $fnDate AS d, COUNT(*) AS cnt
               FROM logs
               $whereSql
               GROUP BY $fnDate
               ORDER BY d DESC
               LIMIT 30";
    $stmtD = $db->prepare($sqlDay);
    foreach ($params as $k => $v) { $stmtD->bindValue($k, $v); }
    $stmtD->execute();
    $byDay = array_reverse($stmtD->fetchAll(PDO::FETCH_ASSOC)); // aufsteigend anzeigen

    // Verteilung nach Wochentag
    $sqlDow = "SELECT $fnDow AS dow, COUNT(*) AS cnt
               FROM logs
               $whereSql
               GROUP BY $fnDow
               ORDER BY dow";
    $stmtW = $db->prepare($sqlDow);
    foreach ($params as $k => $v) { $stmtW->bindValue($k, $v); }
    $stmtW->execute();
    $byDow = $stmtW->fetchAll(PDO::FETCH_ASSOC);

  } catch (Throwable $ex) {
    $fatalError = $ex->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Analyse der Anfragen</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
</head>
<body class="analytics-page">
  <div class="top-actions">
    <h1>Analyse – <?php echo htmlspecialchars($HOTEL_NAME); ?></h1>
    <div>
      <a class="button" href="<?php echo htmlspecialchars($exportUrl); ?>">CSV exportieren</a>
      <a class="button ghost" href="admin.php">Zurück</a>
    </div>
  </div>

  <form method="get" class="filters">
    <div class="field">
      <label for="start" class="muted">Startdatum</label>
      <input type="date" id="start" name="start" value="<?php echo htmlspecialchars($start); ?>">
    </div>
    <div class="field">
      <label for="end" class="muted">Enddatum</label>
      <input type="date" id="end" name="end" value="<?php echo htmlspecialchars($end); ?>">
    </div>
    <div class="field">
      <label for="q" class="muted">Suche (Frage enthält)</label>
      <input type="text" id="q" name="q" placeholder="z. B. Frühstück, Spa, Parkplatz" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field">
      <label for="limit" class="muted">Anzahl letzter Einträge</label>
      <input type="number" id="limit" name="limit" min="10" max="1000" step="10" value="<?php echo (int)$limit; ?>">
    </div>
    <div class="field">
      <button type="submit">Filter anwenden</button>
    </div>
  </form>

  <?php if (!empty($fatalError)): ?>
    <div class="card span-12" style="border-color:#ef4444">
      <h2 style="color:#ef8888;margin-top:0;">Fehler bei der Datenabfrage</h2>
      <div class="muted"><?php echo htmlspecialchars($fatalError); ?></div>
      <p class="muted">Tipp: Seite mit <code>?debug=1</code> aufrufen. (SQLite aktiv)</p>
    </div>
  <?php endif; ?>

  <div class="grid">
    <!-- KPIs -->
    <div class="kpis">
      <div class="kpi">
        <div class="label">Gesamt</div>
        <div class="value"><?php echo number_format($totals['total'], 0, ',', '.'); ?></div>
      </div>
      <div class="kpi">
        <div class="label">Leere Antworten</div>
        <div class="value" title="Antwort ist leer / nicht vorhanden">
          <?php echo number_format($totals['empty'], 0, ',', '.'); ?>
        </div>
      </div>
      <div class="kpi">
        <div class="label">Ø Zeichen Frage</div>
        <div class="value"><?php echo number_format($totals['avg_q_len'], 0, ',', '.'); ?></div>
      </div>
      <div class="kpi">
        <div class="label">Ø Zeichen Antwort</div>
        <div class="value"><?php echo number_format($totals['avg_a_len'], 0, ',', '.'); ?></div>
      </div>
    </div>

    <!-- Verlauf letzte 30 Tage -->
    <div class="card span-12">
      <h2>Aktivitätsverlauf (letzte 30 Tage)</h2>
      <table>
        <thead><tr><th>Datum</th><th>Anfragen</th></tr></thead>
        <tbody>
          <?php foreach ($byDay as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['d']); ?></td>
              <td><?php echo htmlspecialchars($row['cnt']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Stoßzeiten nach Stunde -->
    <div class="card span-6">
      <h2>Stoßzeiten (Stunde des Tages)</h2>
      <div class="bars">
        <?php
          $maxHour = 0;
          foreach ($byHour as $r) { if ((int)$r['cnt'] > $maxHour) { $maxHour = (int)$r['cnt']; } }
          foreach ($byHour as $r):
            $w = $maxHour ? intval(((int)$r['cnt'] / $maxHour) * 100) : 0;
        ?>
          <div class="bar">
            <div class="label"><?php echo str_pad((string)$r['h'], 2, '0', STR_PAD_LEFT) . ':00'; ?></div>
            <div class="track"><div class="fill" style="width: <?php echo $w; ?>%"></div></div>
            <div class="label"><?php echo (int)$r['cnt']; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Wochentage -->
    <div class="card span-6">
      <h2>Verteilung nach Wochentag</h2>
      <div class="bars">
        <?php
          $dowNames = [0=>'So',1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa'];
          $maxDow = 0;
          foreach ($byDow as $r) { if ((int)$r['cnt'] > $maxDow) { $maxDow = (int)$r['cnt']; } }
          foreach ($byDow as $r):
            $name = isset($dowNames[(int)$r['dow']]) ? $dowNames[(int)$r['dow']] : (string)$r['dow'];
            $w = $maxDow ? intval(((int)$r['cnt'] / $maxDow) * 100) : 0;
        ?>
          <div class="bar">
            <div class="label"><?php echo htmlspecialchars($name); ?></div>
            <div class="track"><div class="fill" style="width: <?php echo $w; ?>%"></div></div>
            <div class="label"><?php echo (int)$r['cnt']; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Top 20 Fragen -->
    <div class="card span-6">
      <h2>Häufig gestellte Fragen (Top 20)</h2>
      <table>
        <thead><tr><th>Frage</th><th>Anzahl</th></tr></thead>
        <tbody>
          <?php foreach ($stats as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['question']); ?></td>
              <td><?php echo htmlspecialchars($row['cnt']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Letzte Anfragen -->
    <div class="card span-6">
      <h2>Letzte Anfragen</h2>
      <table>
        <thead><tr><th>Zeit</th><th>Frage</th><th>Antwort</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['time']); ?></td>
              <td><?php echo htmlspecialchars($row['question']); ?></td>
              <td><?php echo nl2br(htmlspecialchars($row['answer'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>