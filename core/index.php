<?php
/**
 * Hauptseite des Chatbots.
 *
 * Dieses Skript wird von den hotelspezifischen index.php-Dateien eingebunden. Es
 * erwartet, dass die Variable $configPath definiert und vor dem Einbinden
 * gesetzt wurde. init.php lädt die Konfiguration und stellt $HOTEL_NAME,
 * $LOGO_PATH, $HOTEL_URL und weitere Parameter bereit.
 */

require_once __DIR__ . '/init.php';

// Relativer Pfad zum Core‑Verzeichnis aus Sicht des Hotels. Dieser wird im
// Hotel‑Wrapper (index.php) gesetzt. Fallback: '..' falls nicht gesetzt.
$coreRelative = $coreRelative ?? '..';

// Hotelspezifisches Stylesheet aus der Konfiguration übernehmen, falls der
// Wrapper keine eigene Variable gesetzt hat.
$hotelCssUrl = $hotelCssUrl ?? ($HOTEL_CSS_URL ?? null);

// Ermitteln des relativen Pfads zum Logo innerhalb des Webservers
$logoSrc = null;
if (isset($LOGO_PATH) && file_exists($LOGO_PATH)) {
    // basename() sorgt dafür, dass nur der Dateiname im <img> verwendet wird
    $logoSrc = basename($LOGO_PATH);
}

// Hintergrund optional setzen: Falls im Hotellogo eine zusätzliche Bilddatei
// vorhanden sein sollte, kann sie in CSS oder per Inline‑Style referenziert
// werden. Hier laden wir kein spezielles Bild, sondern überlassen es dem
// Benutzer, dies anzupassen.
?>
<!DOCTYPE html>
<html lang="de">
	<script>
  window.CHATBOT = {
    botName: <?php echo json_encode($BOT_NAME ?? 'Max'); ?>
  };
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($HOTEL_NAME); ?> Chatbot</title>
    <!-- Stylesheet aus dem Core laden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
    <?php
    // Wenn der Hotel-Wrapper eine eigene CSS-Datei definiert hat (z. B. assets/css/hotel.css),
    // wird sie hier eingebunden. Dadurch lassen sich Farben und Layout einfach je
    // Standort anpassen, ohne den Core zu verändern.
    if (isset($hotelCssUrl) && $hotelCssUrl) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($hotelCssUrl) . '">';
    }
    ?>
    <style>
    /* Optionaler Inline‑Hintergrund: kann in der hotelspezifischen config überschrieben werden */
    body {
        background-image: url('<?php echo htmlspecialchars($coreRelative); ?>/assets/images/background.jpg');
    }
    </style>
</head>
<body>
    <div class="chat-overlay">
        <div class="chat-box">
            <header>
                <?php if ($logoSrc): ?>
                    <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Logo">
                <?php else: ?>
                    <h2><?php echo htmlspecialchars($HOTEL_NAME); ?></h2>
                <?php endif; ?>
            </header>
            <div id="chat-log"></div>
            <form id="chat-form" class="chat-controls">
                <input type="text" id="chat-input" placeholder="Ihre Frage..." autocomplete="off">
                <div class="privacy">
                    <input type="checkbox" id="privacy-confirm">
                    <label for="privacy-confirm">Ich habe die <a href="<?php echo htmlspecialchars($privacyUrl ?? 'privacy.php'); ?>" target="_blank" rel="noopener">Datenschutzinformationen</a> gelesen und akzeptiere sie.</label>
                </div>
                <button type="submit" id="chat-send" disabled>Senden</button>
            </form>
        </div>
    </div>
    <a class="return-link" href="<?php echo htmlspecialchars($HOTEL_URL); ?>">Zurück zur Website</a>
    <script src="<?php echo htmlspecialchars($coreRelative); ?>/assets/js/chat.js"></script>
</body>
</html>