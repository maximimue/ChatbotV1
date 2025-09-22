<?php
/**
 * Gibt dynamische Style-Overrides aus, die über die Konfiguration gesetzt werden können.
 */
$styleVariables = [];
$colorMap = [
    'CHAT_BACKGROUND_COLOR'     => '--chat-background-color',
    'CHAT_BOX_BACKGROUND_COLOR' => '--chat-box-background-color',
    'CHAT_PRIMARY_COLOR'        => '--chat-primary-color',
    'CHAT_PRIMARY_TEXT_COLOR'   => '--chat-primary-text-color',
    'CHAT_USER_BUBBLE_COLOR'    => '--chat-user-bubble-color',
    'CHAT_USER_TEXT_COLOR'      => '--chat-user-text-color',
    'CHAT_BOT_BUBBLE_COLOR'     => '--chat-bot-bubble-color',
    'CHAT_BOT_TEXT_COLOR'       => '--chat-bot-text-color',
    'CHAT_LINK_COLOR'           => '--chat-link-color',
];

foreach ($colorMap as $configKey => $cssVar) {
    if (isset($$configKey) && is_string($$configKey) && $$configKey !== '') {
        $styleVariables[] = $cssVar . ': ' . htmlspecialchars((string)$$configKey, ENT_QUOTES);
    }
}

$backgroundImageUrl = null;
if (isset($BACKGROUND_IMAGE_URL) && $BACKGROUND_IMAGE_URL !== '') {
    $backgroundImageUrl = chatbot_asset_url((string)$BACKGROUND_IMAGE_URL, $HOTEL_BASE_PATH ?? null);
}

if (!empty($styleVariables) || $backgroundImageUrl) {
    echo "<style>\n";
    if (!empty($styleVariables)) {
        echo ":root {\n";
        foreach ($styleVariables as $line) {
            echo '    ' . $line . "\n";
        }
        echo "}\n";
    }
    if ($backgroundImageUrl) {
        $escaped = htmlspecialchars($backgroundImageUrl, ENT_QUOTES);
        echo "body { background-image: url('{$escaped}'); }\n";
    }
    echo "</style>\n";
}
