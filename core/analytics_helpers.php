<?php
/**
 * Hilfsfunktionen zur Analyse der Chat-Logs.
 */

/**
 * Normalisiert Filterparameter für die Analytics-Abfragen.
 *
 * @param array<string,mixed> $input
 * @return array{start:string,end:string,q:string,limit:int}
 */
function analytics_normalize_filters(array $input): array
{
    $start = isset($input['start']) ? trim((string)$input['start']) : '';
    $end   = isset($input['end'])   ? trim((string)$input['end'])   : '';
    $q     = isset($input['q'])     ? trim((string)$input['q'])     : '';
    $limit = isset($input['limit']) ? (int)$input['limit'] : 100;
    if ($limit < 10 || $limit > 1000) {
        $limit = 100;
    }

    return [
        'start' => $start,
        'end'   => $end,
        'q'     => $q,
        'limit' => $limit,
    ];
}

/**
 * Erstellt WHERE-Klausel und Parameter für die Filter.
 *
 * @param array{start:string,end:string,q:string,limit:int} $filters
 * @return array{sql:string,params:array<string,string>}
 */
function analytics_build_where(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['start'] !== '') {
        $where[] = "time >= :start";
        $params[':start'] = $filters['start'] . ' 00:00:00';
    }
    if ($filters['end'] !== '') {
        $where[] = "time <= :end";
        $params[':end'] = $filters['end'] . ' 23:59:59';
    }
    if ($filters['q'] !== '') {
        $where[] = "question LIKE :q";
        $params[':q'] = '%' . $filters['q'] . '%';
    }
    $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    return ['sql' => $sql, 'params' => $params];
}

/**
 * Führt alle notwendigen Datenbankabfragen aus und gibt die Ergebnisse zurück.
 *
 * @param PDO|null $db
 * @param array{start:string,end:string,q:string,limit:int} $filters
 * @return array{
 *   stats:array<int,array<string,mixed>>,
 *   logs:array<int,array<string,mixed>>,
 *   byHour:array<int,array<string,mixed>>,
 *   byDay:array<int,array<string,mixed>>,
 *   byDow:array<int,array<string,mixed>>,
 *   totals:array<string,int>,
 *   fatalError:?string
 * }
 */
function analytics_fetch_data(?PDO $db, array $filters): array
{
    $stats = [];
    $logs  = [];
    $byHour = [];
    $byDay  = [];
    $byDow  = [];
    $totals = ['total'=>0,'empty'=>0,'avg_q_len'=>0,'avg_a_len'=>0];
    $fatalError = null;

    if (!$db instanceof PDO) {
        return compact('stats', 'logs', 'byHour', 'byDay', 'byDow', 'totals', 'fatalError');
    }

    $parts = analytics_build_where($filters);
    $whereSql = $parts['sql'];
    $params = $parts['params'];

    // SQLite spezifische Funktionen
    $fnDate = "strftime('%Y-%m-%d', time)";
    $fnHour = "CAST(strftime('%H', time) AS INTEGER)";
    $fnDow  = "CAST(strftime('%w', time) AS INTEGER)";
    $fnQLen = "LENGTH(question)";
    $fnALen = "LENGTH(answer)";

    try {
        // Top-Fragen
        $sqlTop = "SELECT question, COUNT(*) AS cnt
                   FROM logs
                   $whereSql
                   GROUP BY question
                   ORDER BY cnt DESC
                   LIMIT 20";
        $stmtTop = $db->prepare($sqlTop);
        foreach ($params as $k => $v) { $stmtTop->bindValue($k, $v); }
        $stmtTop->execute();
        $stats = $stmtTop->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Letzte Einträge
        $sqlLast = "SELECT question, answer, time
                    FROM logs
                    $whereSql
                    ORDER BY time DESC
                    LIMIT :limit";
        $stmtLast = $db->prepare($sqlLast);
        foreach ($params as $k => $v) { $stmtLast->bindValue($k, $v); }
        $stmtLast->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
        $stmtLast->execute();
        $logs = $stmtLast->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Kennzahlen
        $sqlTotals = "SELECT COUNT(*) AS total,
                             SUM(CASE WHEN (answer IS NULL OR answer='') THEN 1 ELSE 0 END) AS empty_cnt,
                             AVG($fnQLen) AS avg_q_len,
                             AVG($fnALen) AS avg_a_len
                      FROM logs
                      $whereSql";
        $stmtTotals = $db->prepare($sqlTotals);
        foreach ($params as $k => $v) { $stmtTotals->bindValue($k, $v); }
        $stmtTotals->execute();
        $row = $stmtTotals->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $totals['total']     = (int)$row['total'];
            $totals['empty']     = (int)$row['empty_cnt'];
            $totals['avg_q_len'] = (int)round((float)$row['avg_q_len']);
            $totals['avg_a_len'] = (int)round((float)$row['avg_a_len']);
        }

        // Stundenverteilung
        $sqlHour = "SELECT $fnHour AS h, COUNT(*) AS cnt
                    FROM logs
                    $whereSql
                    GROUP BY $fnHour
                    ORDER BY h";
        $stmtHour = $db->prepare($sqlHour);
        foreach ($params as $k => $v) { $stmtHour->bindValue($k, $v); }
        $stmtHour->execute();
        $byHour = $stmtHour->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Tagesverlauf
        $sqlDay = "SELECT $fnDate AS d, COUNT(*) AS cnt
                   FROM logs
                   $whereSql
                   GROUP BY $fnDate
                   ORDER BY d DESC
                   LIMIT 30";
        $stmtDay = $db->prepare($sqlDay);
        foreach ($params as $k => $v) { $stmtDay->bindValue($k, $v); }
        $stmtDay->execute();
        $byDay = array_reverse($stmtDay->fetchAll(PDO::FETCH_ASSOC) ?: []);

        // Wochentage
        $sqlDow = "SELECT $fnDow AS dow, COUNT(*) AS cnt
                   FROM logs
                   $whereSql
                   GROUP BY $fnDow
                   ORDER BY dow";
        $stmtDow = $db->prepare($sqlDow);
        foreach ($params as $k => $v) { $stmtDow->bindValue($k, $v); }
        $stmtDow->execute();
        $byDow = $stmtDow->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $ex) {
        $fatalError = $ex->getMessage();
    }

    return compact('stats', 'logs', 'byHour', 'byDay', 'byDow', 'totals', 'fatalError');
}

/**
 * Gibt einen CSV-Export der Chat-Logs aus und beendet das Skript.
 *
 * @param PDO|null $db
 * @param array{start:string,end:string,q:string,limit:int} $filters
 * @return never
 */
function analytics_export_csv(?PDO $db, array $filters): void
{
    if (!$db instanceof PDO) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Keine Datenbankverbindung verfügbar.";
        exit;
    }

    $parts = analytics_build_where($filters);
    $whereSql = $parts['sql'];
    $params = $parts['params'];

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="chat_logs.csv"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fputcsv($out, ['time', 'question', 'answer']);

    $sql = "SELECT time, question, answer
            FROM logs
            $whereSql
            ORDER BY time DESC
            LIMIT 10000";
    $stmt = $db->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['time'], $row['question'], $row['answer']]);
    }
    fclose($out);
    exit;
}

/**
 * Baut eine Query-String-Repräsentation der Filter.
 *
 * @param array{start:string,end:string,q:string,limit:int} $filters
 * @return string
 */
function analytics_query_string(array $filters): string
{
    $params = [];
    foreach (['start','end','q','limit'] as $key) {
        if ($filters[$key] !== '' && $filters[$key] !== null) {
            $params[$key] = $filters[$key];
        }
    }
    return http_build_query($params);
}
