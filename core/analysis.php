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

// Daten vorbereiten (Filter, Statistiken, Export)
$stats = [];
$logs  = [];
$byHour = [];
$byDay = [];
$byDow = [];
$totals = ['total'=>0,'empty'=>0,'avg_q_len'=>0,'avg_a_len'=>0];

// Eingabeparameter (Filter)
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end   = isset($_GET['end'])   ? trim($_GET['end'])   : '';
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit']  : 100;
if ($limit < 10 || $limit > 1000) { $limit = 100; }

// WHERE-Klausel aufbauen
$where = [];
$params = [];
if ($start !== '') { $where[] = 'time >= :start'; $params[':start'] = $start . ' 00:00:00'; }
if ($end   !== '') { $where[] = 'time <= :end';   $params[':end']   = $end   . ' 23:59:59'; }
if ($q     !== '') { $where[] = 'question LIKE :q'; $params[':q']     = '%' . $q . '%'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Export CSV Handler
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

// Query-Parameter für Links (Export-URL)
$queryParams = http_build_query(array_filter([
    'start' => $start,
    'end'   => $end,
    'q'     => $q,
    'limit' => $limit
], function($v){ return $v !== null && $v !== ''; }));
$exportUrl = $_SERVER['PHP_SELF'] . '?' . ($queryParams ? ($queryParams . '&') : '') . 'export=csv';

if (isset($db) && $db instanceof PDO) {
    // Top 20 Fragen
    $sqlTop = "SELECT question, COUNT(*) AS cnt FROM logs $whereSql GROUP BY question ORDER BY cnt DESC LIMIT 20";
    $stmt = $db->prepare($sqlTop);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Letzte Einträge
    $sqlLast = "SELECT question, answer, time FROM logs $whereSql ORDER BY time DESC LIMIT :limit";
    $stmt2 = $db->prepare($sqlLast);
    foreach ($params as $k => $v) { $stmt2->bindValue($k, $v); }
    $stmt2->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt2->execute();
    $logs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Totals (Gesamtanzahl, leere Antworten, durchschnittliche Längen)
    $sqlTotals = "SELECT COUNT(*) AS total,
                         SUM(CASE WHEN (answer IS NULL OR answer='') THEN 1 ELSE 0 END) AS empty_cnt,
                         AVG(CHAR_LENGTH(question)) AS avg_q_len,
                         AVG(CHAR_LENGTH(answer)) AS avg_a_len
                  FROM logs $whereSql";
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

    // Verteilung nach Stunde (Stoßzeiten)
    $sqlHour = "SELECT HOUR(time) AS h, COUNT(*) AS cnt FROM logs $whereSql GROUP BY HOUR(time) ORDER BY h";
    $stmtH = $db->prepare($sqlHour);
    foreach ($params as $k => $v) { $stmtH->bindValue($k, $v); }
    $stmtH->execute();
    $byHour = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    // Verlauf je Tag (letzte 30 Tage)
    $sqlDay = "SELECT DATE(time) AS d, COUNT(*) AS cnt FROM logs $whereSql GROUP BY DATE(time) ORDER BY d DESC LIMIT 30";
    $stmtD = $db->prepare($sqlDay);
    foreach ($params as $k => $v) { $stmtD->bindValue($k, $v); }
    $stmtD->execute();
    $byDay = array_reverse($stmtD->fetchAll(PDO::FETCH_ASSOC)); // für zeitlich aufsteigende Darstellung

    // Wochentage (1=Sonntag in MySQL, wir mappen gleich im UI)
    $sqlDow = "SELECT DAYOFWEEK(time) AS dow, COUNT(*) AS cnt FROM logs $whereSql GROUP BY DAYOFWEEK(time) ORDER BY dow";
    $stmtW = $db->prepare($sqlDow);
    foreach ($params as $k => $v) { $stmtW->bindValue($k, $v); }
    $stmtW->execute();
    $byDow = $stmtW->fetchAll(PDO::FETCH_ASSOC);
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
    :root{
      --bg:#0f1115; --card:#151924; --muted:#8a93a6; --text:#e5e7ee; --accent:#3b82f6; --ok:#22c55e; --warn:#f59e0b; --bad:#ef4444; --border:#232836;
    }
    *{box-sizing:border-box}
    body{
      padding:24px; margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, Helvetica, sans-serif;
      background:var(--bg); color:var(--text);
    }
    h1{font-size:28px; margin:0 0 16px;}
    h2{font-size:18px; margin:24px 0 12px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em;}
    a{color:var(--accent); text-decoration:none}
    a:hover{text-decoration:underline}
    .grid{
      display:grid; gap:16px;
      grid-template-columns: repeat(12, 1fr);
    }
    .card{
      background:var(--card); border:1px solid var(--border); border-radius:12px; padding:16px;
    }
    .kpis{grid-column: span 12; display:grid; grid-template-columns: repeat(4,1fr); gap:12px;}
    .kpi{background:linear-gradient(180deg, rgba(255,255,255,.03), transparent); border-radius:12px; border:1px solid var(--border); padding:14px;}
    .kpi .label{font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em;}
    .kpi .value{font-size:24px; font-weight:600; margin-top:6px;}

    .filters{grid-column: span 12; display:flex; gap:12px; flex-wrap:wrap; align-items:end; margin-bottom:4px;}
    .filters .field{display:flex; flex-direction:column; gap:6px;}
    .filters input[type="date"], .filters input[type="text"], .filters input[type="number"]{
      background:#0b0e14; border:1px solid var(--border); color:var(--text); padding:8px 10px; border-radius:8px; min-width:200px;
    }
    .filters button, .filters .button{
      padding:10px 14px; border-radius:8px; border:1px solid var(--border); background:var(--accent); color:white; cursor:pointer;
    }
    .filters .ghost{background:transparent; color:var(--text);}
    .flex{display:flex; gap:16px; flex-wrap:wrap}
    .span-6{grid-column: span 6;} .span-4{grid-column: span 4;} .span-12{grid-column: span 12;}

    table{width:100%; border-collapse: collapse;}
    th, td{border-bottom:1px solid var(--border); padding:10px; text-align:left;}
    th{color:var(--muted); font-weight:600; text-transform:uppercase; font-size:12px; letter-spacing:.06em;}
    tbody tr:hover{background:#101520;}

    .bars{display:flex; flex-direction:column; gap:8px;}
    .bar{display:flex; align-items:center; gap:12px;}
    .bar .label{width:80px; color:var(--muted);}
    .bar .track{flex:1; height:10px; background:#0b0e14; border:1px solid var(--border); border-radius:999px; overflow:hidden;}
    .bar .fill{height:100%; background:var(--accent);}
    .muted{color:var(--muted)}
    .top-actions{display:flex; gap:12px; align-items:center; justify-content:space-between; margin-bottom:8px;}
    </style>
</head>
<body>
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
      <input type="text" id="q" name="q" placeholder="z. B. Frühstück, Spa, Parkplatz" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="field">
      <label for="limit" class="muted">Anzahl letzter Einträge</label>
      <input type="number" id="limit" name="limit" min="10" max="1000" step="10" value="<?php echo (int)$limit; ?>">
    </div>
    <div class="field">
      <button type="submit">Filter anwenden</button>
    </div>
  </form>

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
            $w = $maxHour ? intval(($r['cnt'] / $maxHour) * 100) : 0;
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
          $dowNames = [1=>'So',2=>'Mo',3=>'Di',4=>'Mi',5=>'Do',6=>'Fr',7=>'Sa'];
          $maxDow = 0;
          foreach ($byDow as $r) { if ((int)$r['cnt'] > $maxDow) { $maxDow = (int)$r['cnt']; } }
          foreach ($byDow as $r):
            $name = isset($dowNames[(int)$r['dow']]) ? $dowNames[(int)$r['dow']] : (string)$r['dow'];
            $w = $maxDow ? intval(($r['cnt'] / $maxDow) * 100) : 0;
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