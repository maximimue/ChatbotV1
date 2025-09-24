<?php
/**
 * Gibt dynamische Style-Overrides aus, die über die Konfiguration gesetzt werden können.
 */
$styleVariables = [];
$colorMap = [
    'THEME_COLOR_BASE'             => '--theme-color-base',
    'THEME_COLOR_SURFACE'          => '--theme-color-surface',
    'THEME_COLOR_PRIMARY'          => '--theme-color-primary',
    'THEME_COLOR_PRIMARY_CONTRAST' => '--theme-color-primary-contrast',
    'THEME_COLOR_TEXT'             => '--theme-color-text',
];

$themeDefaults = [
    'THEME_COLOR_BASE'             => '#F0F0F0',
    'THEME_COLOR_SURFACE'          => '#FFFFFF',
    'THEME_COLOR_PRIMARY'          => '#003366',
    'THEME_COLOR_PRIMARY_CONTRAST' => '#FFFFFF',
    'THEME_COLOR_TEXT'             => '#0F172A',
];

foreach ($colorMap as $configKey => $cssVar) {
    $value = isset($$configKey) ? chatbot_normalize_hex_color((string)$$configKey) : null;

    if ($value === null) {
        continue;
    }

    $default = $themeDefaults[$configKey] ?? null;
    if ($default !== null && $value === $default) {
        continue;
    }

    $styleVariables[] = $cssVar . ': ' . htmlspecialchars($value, ENT_QUOTES);
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
