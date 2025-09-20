<?php
/**
 * Gemeinsamer Markup-Teil für die Analyse-Ansicht.
 *
 * Erwartet die Variablen:
 * - $analysisFilters (array)
 * - $analysisData (array)
 * - $exportUrl (string)
 * - $coreRelative (string)
 * - $HOTEL_NAME (string)
 * - $backLink (string|null)
 */
?>
<div class="analytics-page">
  <div class="top-actions">
    <h1>Analyse – <?php echo htmlspecialchars($HOTEL_NAME); ?></h1>
    <div>
      <a class="button" href="<?php echo htmlspecialchars($exportUrl); ?>">CSV exportieren</a>
      <?php if (!empty($backLink)): ?>
        <a class="button ghost" href="<?php echo htmlspecialchars($backLink); ?>">Zurück</a>
      <?php endif; ?>
    </div>
  </div>

  <form method="get" class="filters">
    <?php if (isset($analysisFilters['tab'])): ?>
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($analysisFilters['tab']); ?>">
    <?php endif; ?>
    <div class="field">
      <label for="start" class="muted">Startdatum</label>
      <input type="date" id="start" name="start" value="<?php echo htmlspecialchars($analysisFilters['start']); ?>">
    </div>
    <div class="field">
      <label for="end" class="muted">Enddatum</label>
      <input type="date" id="end" name="end" value="<?php echo htmlspecialchars($analysisFilters['end']); ?>">
    </div>
    <div class="field">
      <label for="q" class="muted">Suche (Frage enthält)</label>
      <input type="text" id="q" name="q" placeholder="z. B. Frühstück, Spa, Parkplatz" value="<?php echo htmlspecialchars($analysisFilters['q']); ?>">
    </div>
    <div class="field">
      <label for="limit" class="muted">Anzahl letzter Einträge</label>
      <input type="number" id="limit" name="limit" min="10" max="1000" step="10" value="<?php echo (int)$analysisFilters['limit']; ?>">
    </div>
    <div class="field">
      <button type="submit">Filter anwenden</button>
    </div>
  </form>

  <?php if (!empty($analysisData['fatalError'])): ?>
    <div class="card span-12" style="border-color:#ef4444">
      <h2 style="color:#ef8888;margin-top:0;">Fehler bei der Datenabfrage</h2>
      <div class="muted"><?php echo htmlspecialchars($analysisData['fatalError']); ?></div>
      <p class="muted">Tipp: Seite mit <code>?debug=1</code> aufrufen. (SQLite aktiv)</p>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="kpis">
      <div class="kpi">
        <div class="label">Gesamt</div>
        <div class="value"><?php echo number_format($analysisData['totals']['total'] ?? 0, 0, ',', '.'); ?></div>
      </div>
      <div class="kpi">
        <div class="label">Leere Antworten</div>
        <div class="value" title="Antwort ist leer / nicht vorhanden">
          <?php echo number_format($analysisData['totals']['empty'] ?? 0, 0, ',', '.'); ?>
        </div>
      </div>
      <div class="kpi">
        <div class="label">Ø Zeichen Frage</div>
        <div class="value"><?php echo number_format($analysisData['totals']['avg_q_len'] ?? 0, 0, ',', '.'); ?></div>
      </div>
      <div class="kpi">
        <div class="label">Ø Zeichen Antwort</div>
        <div class="value"><?php echo number_format($analysisData['totals']['avg_a_len'] ?? 0, 0, ',', '.'); ?></div>
      </div>
    </div>

    <div class="card span-12">
      <h2>Aktivitätsverlauf (letzte 30 Tage)</h2>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Datum</th><th>Anfragen</th></tr></thead>
          <tbody>
            <?php foreach ($analysisData['byDay'] as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['d']); ?></td>
                <td><?php echo htmlspecialchars($row['cnt']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card span-6">
      <h2>Stoßzeiten (Stunde des Tages)</h2>
      <div class="bars">
        <?php
          $maxHour = 0;
          foreach ($analysisData['byHour'] as $r) { if ((int)$r['cnt'] > $maxHour) { $maxHour = (int)$r['cnt']; } }
          foreach ($analysisData['byHour'] as $r):
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

    <div class="card span-6">
      <h2>Verteilung nach Wochentag</h2>
      <div class="bars">
        <?php
          $dowNames = [0=>'So',1=>'Mo',2=>'Di',3=>'Mi',4=>'Do',5=>'Fr',6=>'Sa'];
          $maxDow = 0;
          foreach ($analysisData['byDow'] as $r) { if ((int)$r['cnt'] > $maxDow) { $maxDow = (int)$r['cnt']; } }
          foreach ($analysisData['byDow'] as $r):
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

    <div class="card span-6">
      <h2>Häufig gestellte Fragen (Top 20)</h2>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Frage</th><th>Anzahl</th></tr></thead>
          <tbody>
            <?php foreach ($analysisData['stats'] as $row): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['question']); ?></td>
                <td><?php echo htmlspecialchars($row['cnt']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card span-6">
      <h2>Letzte Anfragen</h2>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Zeit</th><th>Frage</th><th>Antwort</th></tr></thead>
          <tbody>
            <?php foreach ($analysisData['logs'] as $row): ?>
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
  </div>
</div>
