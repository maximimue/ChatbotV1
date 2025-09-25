<?php
/**
 * Gibt dynamische Style-Overrides aus, die über die Konfiguration gesetzt werden können.
 */
$styleVariables = [];

$themeDefaults = [
    'THEME_COLOR_BASE'             => '#F0F0F0',
    'THEME_COLOR_SURFACE'          => '#FFFFFF',
    'THEME_COLOR_PRIMARY'          => '#003366',
    'THEME_COLOR_PRIMARY_CONTRAST' => '#FFFFFF',
    'THEME_COLOR_TEXT'             => '#0F172A',
];

$legacyTranslations = [
    'THEME_COLOR_BASE'             => $CHAT_BACKGROUND_COLOR ?? null,
    'THEME_COLOR_SURFACE'          => $CHAT_BOX_BACKGROUND_COLOR ?? null,
    'THEME_COLOR_PRIMARY'          => $CHAT_PRIMARY_COLOR ?? null,
    'THEME_COLOR_PRIMARY_CONTRAST' => $CHAT_PRIMARY_TEXT_COLOR ?? null,
    'THEME_COLOR_TEXT'             => $CHAT_BOT_TEXT_COLOR ?? null,
];

foreach ($themeDefaults as $themeKey => $defaultHex) {
    $value = isset(${$themeKey}) ? chatbot_normalize_hex_color((string)${$themeKey}) : null;

    if ($value === null) {
        $legacy = $legacyTranslations[$themeKey] ?? null;
        if (is_string($legacy)) {
            $value = chatbot_normalize_hex_color($legacy);
        }
    }

    if ($value === null || $value === $defaultHex) {
        continue;
    }

    $cssVariable = '--' . strtolower(strtr($themeKey, [
        'THEME_' => 'theme-',
        '_'      => '-',
    ]));

    $styleVariables[] = $cssVariable . ': ' . htmlspecialchars($value, ENT_QUOTES);
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
