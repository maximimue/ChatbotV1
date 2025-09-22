<?php
/**
 * Zentrale Datenschutzerklärung für den Chatbot.
 *
 * Die Seite übernimmt das grundlegende Erscheinungsbild des Chat-Overlays und
 * stellt die vom Kunden gewünschte Datenschutzerklärung bereit. Sie wird von
 * den hotelspezifischen Wrappern eingebunden, die den Pfad zur jeweiligen
 * Konfiguration setzen.
 */

require_once __DIR__ . '/init.php';

$coreRelative = $coreRelative ?? '..';
$hotelCssUrl = $hotelCssUrl ?? ($HOTEL_CSS_URL ?? null);

$logoSrc = null;
if (isset($LOGO_PATH) && file_exists($LOGO_PATH)) {
    $logoSrc = basename($LOGO_PATH);
}

$chatReturnUrl = $chatReturnUrl ?? 'index.php';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenschutz &ndash; <?php echo htmlspecialchars($HOTEL_NAME); ?> Chatbot</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
    <?php
    if (isset($hotelCssUrl) && $hotelCssUrl) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($hotelCssUrl) . '">';
    }
    ?>
</head>
<body class="privacy-page">
    <div class="chat-overlay">
        <div class="chat-box privacy-box">
            <header>
                <?php if ($logoSrc): ?>
                    <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="Logo">
                <?php else: ?>
                    <h2><?php echo htmlspecialchars($HOTEL_NAME); ?></h2>
                <?php endif; ?>
            </header>
            <div class="privacy-content" role="document">
                <h1>Datenschutzerklärung für den Chatbot</h1>
                <p>Der Schutz Ihrer Daten ist uns wichtig. Nachfolgend informieren wir Sie über die Verarbeitung personenbezogener Daten im Zusammenhang mit der Nutzung des Chatbots.</p>

                <h2>Verantwortlicher</h2>
                <address>
                    Syltwerk<br>
                    Maximilian Müller<br>
                    Friesische Straße 85d<br>
                    25980 Sylt<br>
                    E-Mail: <a href="mailto:info@syltwerk.de">info@syltwerk.de</a>
                </address>

                <h2>Zweck der Datenverarbeitung</h2>
                <p>Der Chatbot dient dazu, Gästen und Interessierten Fragen zu unseren Hotels und Angeboten zu beantworten. Hierzu werden die vom Nutzer eingegebenen Fragen verarbeitet und eine passende Antwort zurückgegeben.</p>

                <h2>Welche Daten werden verarbeitet</h2>
                <ul>
                    <li>vom Nutzer eingegebene Fragen</li>
                    <li>durch den Chatbot generierte Antworten</li>
                </ul>
                <p>Eine Speicherung von IP-Adressen oder sonstigen Verbindungsdaten erfolgt nicht. Personenbezogene Daten werden nicht aktiv abgefragt. Dennoch kann es sein, dass Nutzer freiwillig personenbezogene Angaben in eine Frage einfügen.</p>

                <h2>Speicherung und Auswertung</h2>
                <p>Fragen und Antworten werden in einer lokalen Datenbank (SQLite) auf einem Server in Deutschland (IONOS) gespeichert. Diese Speicherung erfolgt ohne zeitliche Begrenzung, um die Funktionsweise des Chatbots zu verbessern und wiederkehrende Fragen auszuwerten.</p>

                <h2>Weitergabe an Dritte / Einsatz von OpenAI</h2>
                <p>Zur Beantwortung von Fragen nutzt der Chatbot die Schnittstelle (API) von ChatGPT, einem Dienst der OpenAI, L.L.C., San Francisco, USA. Dazu werden die eingegebenen Inhalte an OpenAI übermittelt und dort verarbeitet. Eine Übermittlung in die USA findet auf Grundlage der Standardvertragsklauseln der Europäischen Kommission statt.</p>

                <h2>Rechtsgrundlagen</h2>
                <ul>
                    <li>Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse: effiziente Bearbeitung von Gästefragen und Verbesserung des Angebots)</li>
                    <li>Art. 6 Abs. 1 lit. b DSGVO (soweit es sich um Anfragen handelt, die zur Vertragsanbahnung oder Durchführung erforderlich sind)</li>
                </ul>

                <h2>Speicherdauer</h2>
                <p>Die Daten werden ohne automatische Löschfrist gespeichert. Eine Löschung kann jederzeit auf Anfrage erfolgen.</p>

                <h2>Ihre Rechte</h2>
                <p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Widerspruch sowie das Recht auf Datenübertragbarkeit im Rahmen der gesetzlichen Vorgaben. Außerdem haben Sie das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren.</p>

                <h2>Kontakt</h2>
                <p>Bei Fragen zum Datenschutz wenden Sie sich bitte an die oben angegebene Adresse oder per E-Mail an <a href="mailto:info@syltwerk.de">info@syltwerk.de</a>.</p>
            </div>
            <footer class="privacy-footer">
                <a class="privacy-back" href="<?php echo htmlspecialchars($chatReturnUrl); ?>">Zurück zum Chat</a>
            </footer>
        </div>
    </div>
</body>
</html>
