<?php
/**
 * Administrationsoberfläche mit Reitern für Analyse, FAQ und Einstellungen.
 */

session_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/analytics_helpers.php';
require_once __DIR__ . '/admin_accounts.php';

/**
 * Validiert und verschiebt ein hochgeladenes Asset in das Upload-Verzeichnis.
 *
 * @param array<string,mixed> $fileData
 * @param string|null $hotelBasePath
 * @param string $targetSubdir
 * @param string $label
 * @param array<int,string> $errors
 * @return string|null
 */
function admin_handle_asset_upload(array $fileData, ?string $hotelBasePath, string $targetSubdir, string $label, array &$errors): ?string
{
    $errorCode = isset($fileData['error']) ? (int)$fileData['error'] : UPLOAD_ERR_NO_FILE;
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        $errors[] = sprintf('%s konnte nicht hochgeladen werden (Fehlercode %d).', $label, $errorCode);
        return null;
    }

    $tmpName = isset($fileData['tmp_name']) ? (string)$fileData['tmp_name'] : '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        $errors[] = sprintf('Ungültiger Upload für %s.', $label);
        return null;
    }

    if (!$hotelBasePath || !is_string($hotelBasePath)) {
        $errors[] = sprintf('Upload-Verzeichnis für %s konnte nicht ermittelt werden.', $label);
        return null;
    }

    $allowedMimeMap = [
        'image/png'      => 'png',
        'image/jpeg'     => 'jpg',
        'image/pjpeg'    => 'jpg',
        'image/gif'      => 'gif',
        'image/webp'     => 'webp',
        'image/svg+xml'  => 'svg',
    ];

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmpName) ?: null;
            finfo_close($finfo);
        }
    }

    if ($mime === null && function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpName) ?: null;
    }

    if ($mime === null) {
        $imageInfo = @getimagesize($tmpName);
        if (is_array($imageInfo) && isset($imageInfo['mime'])) {
            $mime = (string)$imageInfo['mime'];
        }
    }

    if ($mime === null || !isset($allowedMimeMap[$mime])) {
        $errors[] = sprintf('%s muss ein Bild im Format PNG, JPG, GIF, WebP oder SVG sein.', $label);
        return null;
    }

    $extension = $allowedMimeMap[$mime];

    $originalName = isset($fileData['name']) ? (string)$fileData['name'] : '';
    $baseName = trim((string)pathinfo($originalName, PATHINFO_FILENAME));
    if ($baseName === '') {
        $baseName = strtolower(preg_replace('/\s+/', '-', $label));
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $baseName), '-'));
    if ($slug === '') {
        $slug = 'asset';
    }

    try {
        $randomSuffix = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $randomSuffix = bin2hex(pack('N', mt_rand()));
    }

    $targetDirectory = rtrim($hotelBasePath, '/\\') . '/' . trim($targetSubdir, '/\\');
    if (!is_dir($targetDirectory)) {
        if (!@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            $errors[] = sprintf('Upload-Verzeichnis konnte nicht erstellt werden (%s).', $targetDirectory);
            return null;
        }
    }

    $fileName = $slug . '-' . date('Ymd-His') . '-' . substr($randomSuffix, 0, 12) . '.' . $extension;
    $targetPath = $targetDirectory . '/' . $fileName;

    if (!@move_uploaded_file($tmpName, $targetPath)) {
        $errors[] = sprintf('%s konnte nicht gespeichert werden.', $label);
        return null;
    }

    @chmod($targetPath, 0644);

    $relativeDirectory = trim($targetSubdir, '/\\');
    return ($relativeDirectory !== '' ? $relativeDirectory . '/' : '') . $fileName;
}

/**
 * Filtert Flash-Meldungen für einen Tab.
 *
 * @param array<int,array<string,mixed>> $messages
 * @param string $tab
 * @return array<int,array<string,mixed>>
 */
function admin_filter_flash(array $messages, string $tab): array
{
    $filtered = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        if (($message['tab'] ?? '') === $tab) {
            $filtered[] = $message;
        }
    }
    return $filtered;
}

/**
 * Ermittelt die Byte-Position eines Variablenzuweisungsblocks in einer PHP-Datei.
 *
 * @param string $contents
 * @param string $key
 * @return array{0:int,1:int}|null
 */
function admin_find_config_assignment_span(string $contents, string $key): ?array
{
    $tokens = token_get_all($contents);
    $offset = 0;
    $tokenCount = count($tokens);

    for ($i = 0; $i < $tokenCount; $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;

        if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$' . $key) {
            $assignmentStart = $offset;
            $j = $i + 1;
            $currentOffset = $offset + strlen($text);
            $isAssignment = false;

            while ($j < $tokenCount) {
                $next = $tokens[$j];
                $nextText = is_array($next) ? $next[1] : $next;

                if (is_array($next)) {
                    $nextType = $next[0];
                    if ($nextType === T_WHITESPACE || $nextType === T_COMMENT || $nextType === T_DOC_COMMENT) {
                        $currentOffset += strlen($nextText);
                        $j++;
                        continue;
                    }
                }

                if ($next === '=') {
                    $currentOffset += strlen($nextText);
                    $j++;
                    $isAssignment = true;
                }

                break;
            }

            if (!$isAssignment) {
                $offset += strlen($text);
                continue;
            }

            $depth = 0;
            $inHeredoc = false;
            $endOffset = null;

            for ($k = $j; $k < $tokenCount; $k++) {
                $tok = $tokens[$k];

                if (is_array($tok)) {
                    $tokText = $tok[1];
                    $tokType = $tok[0];

                    if ($tokType === T_START_HEREDOC || (defined('T_START_NOWDOC') && $tokType === T_START_NOWDOC)) {
                        $inHeredoc = true;
                    } elseif ($tokType === T_END_HEREDOC) {
                        $inHeredoc = false;
                    }

                    $currentOffset += strlen($tokText);
                    continue;
                }

                $char = $tok;

                if (!$inHeredoc) {
                    if ($char === '(' || $char === '[' || $char === '{') {
                        $depth++;
                    } elseif ($char === ')' || $char === ']' || $char === '}') {
                        if ($depth > 0) {
                            $depth--;
                        }
                    } elseif ($char === ';' && $depth === 0) {
                        $currentOffset += strlen($char);
                        $endOffset = $currentOffset;
                        break;
                    }
                }

                $currentOffset += strlen($char);
            }

            if ($endOffset !== null) {
                return [$assignmentStart, $endOffset - $assignmentStart];
            }
        }

        $offset += strlen($text);
    }

    return null;
}

/**
 * Aktualisiert definierte Werte in der Konfigurationsdatei.
 *
 * @param string|null $configPath
 * @param array<string,string> $values
 * @param array<int,string> $errors
 */
function admin_update_config_values(?string $configPath, array $values, array &$errors): bool
{
    if (!$configPath || !is_string($configPath)) {
        $errors[] = 'Konfigurationspfad konnte nicht ermittelt werden.';
        return false;
    }

    if (!is_file($configPath)) {
        $errors[] = 'Konfigurationsdatei wurde nicht gefunden.';
        return false;
    }

    $contents = file_get_contents($configPath);
    if ($contents === false) {
        $errors[] = 'Konfigurationsdatei konnte nicht gelesen werden.';
        return false;
    }

    foreach ($values as $key => $value) {
        $exported = var_export($value, true);
        $replacement = '$' . $key . ' = ' . $exported . ';';

        $span = admin_find_config_assignment_span($contents, $key);
        if ($span !== null) {
            [$start, $length] = $span;
            $contents = substr_replace($contents, $replacement, $start, $length);
        } else {
            $contents = rtrim($contents) . PHP_EOL . $replacement . PHP_EOL;
        }
    }

    if (@file_put_contents($configPath, $contents) === false) {
        $errors[] = 'Konfigurationsdatei konnte nicht gespeichert werden.';
        return false;
    }

    return true;
}

/**
 * Normalisiert Hex-Farbwerte (#RRGGBB) für die Admin-Eingabe.
 *
 * @param string|null $value
 * @return string|null
 */
function admin_normalize_hex_color(?string $value): ?string
{
    if (function_exists('chatbot_normalize_hex_color')) {
        return chatbot_normalize_hex_color($value);
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
        return null;
    }

    $hex = strtoupper($matches[1]);
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0]
            . $hex[1] . $hex[1]
            . $hex[2] . $hex[2];
    }

    return '#' . $hex;
}

/**
 * Liest Theme-Farben aus einer CSS-Datei des Hotels aus.
 *
 * @param string|null $cssUrl
 * @param string|null $hotelBasePath
 * @return array<string,string>
 */
function admin_load_theme_colors_from_css(?string $cssUrl, ?string $hotelBasePath): array
{
    if (!is_string($cssUrl)) {
        return [];
    }

    $cssUrl = trim($cssUrl);
    if ($cssUrl === '') {
        return [];
    }

    $parsedUrl = @parse_url($cssUrl);
    if ($parsedUrl === false) {
        $parsedUrl = [];
    }

    $scheme = isset($parsedUrl['scheme']) ? (string)$parsedUrl['scheme'] : '';
    $path = '';

    if ($scheme !== '' && $scheme !== 'file') {
        if (preg_match('/^[A-Za-z]$/', $scheme) === 1 && isset($parsedUrl['path'])) {
            $path = $scheme . ':' . $parsedUrl['path'];
            $scheme = '';
        } else {
            return [];
        }
    }

    if ($scheme === '' || $scheme === 'file') {
        if ($scheme === '' && isset($parsedUrl['host']) && $parsedUrl['host'] !== '') {
            return [];
        }

        if ($path === '') {
            $path = isset($parsedUrl['path']) ? (string)$parsedUrl['path'] : $cssUrl;
        }

        if ($path === '') {
            return [];
        }

        $isAbsolute = ($path[0] ?? '') === '/' || preg_match('/^[A-Za-z]:[\\\/]/', $path) === 1;
        if (!$isAbsolute) {
            if (!is_string($hotelBasePath) || trim($hotelBasePath) === '') {
                return [];
            }

            $path = rtrim($hotelBasePath, '/\\') . '/' . ltrim($path, '/\\');
        }
    } else {
        return [];
    }

    if ($path === '' || !is_readable($path)) {
        return [];
    }

    $cssContent = @file_get_contents($path);
    if (!is_string($cssContent) || $cssContent === '') {
        return [];
    }

    $variableMap = [
        'THEME_COLOR_BASE'             => '--theme-color-base',
        'THEME_COLOR_SURFACE'          => '--theme-color-surface',
        'THEME_COLOR_PRIMARY'          => '--theme-color-primary',
        'THEME_COLOR_PRIMARY_CONTRAST' => '--theme-color-primary-contrast',
        'THEME_COLOR_TEXT'             => '--theme-color-text',
    ];

    $colors = [];
    foreach ($variableMap as $settingKey => $variableName) {
        $pattern = '/' . preg_quote($variableName, '/') . '\s*:\s*([^;]+);/i';
        if (preg_match($pattern, $cssContent, $matches)) {
            $value = trim((string)preg_replace('/!important\b/i', '', $matches[1]));
            $normalized = admin_normalize_hex_color($value);
            if ($normalized !== null) {
                $colors[$settingKey] = $normalized;
            }
        }
    }

    return $colors;
}

/**
 * Ermittelt den anzuzeigenden Theme-Farbwert anhand der aktuellen Konfigurationsschlüssel.
 *
 * @param mixed $primaryValue
 * @param string $default
 * @param string|null $cssFallback
 */
function admin_resolve_theme_color($primaryValue, string $default, ?string $cssFallback = null): string
{
    $normalizedPrimary = admin_normalize_hex_color(is_string($primaryValue) ? $primaryValue : null);
    if ($normalizedPrimary !== null) {
        return $normalizedPrimary;
    }

    $normalizedCss = admin_normalize_hex_color($cssFallback);
    if ($normalizedCss !== null) {
        return $normalizedCss;
    }

    return $default;
}

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

$validTabs = ['analysis', 'faq', 'settings'];
$activeTab = isset($_GET['tab']) ? strtolower((string)$_GET['tab']) : 'analysis';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'analysis';
}

$flashMessages = $_SESSION['admin_flash'] ?? [];
unset($_SESSION['admin_flash']);

$accountsFile = admin_accounts_file_path($HOTEL_BASE_PATH ?? null);
$availableAccounts = admin_load_accounts(
    $accountsFile,
    $ADMIN_USERS ?? null,
    $ADMIN_USER ?? null,
    $ADMIN_PASSWORD_HASH ?? null
);

$faqPath = isset($FAQ_FILE) ? $FAQ_FILE : null;
$faqContent = '';
if ($faqPath && is_readable($faqPath)) {
    $faqContent = (string)file_get_contents($faqPath);
}
$faqError = '';

$cssThemeColors = admin_load_theme_colors_from_css($HOTEL_CSS_URL ?? null, $HOTEL_BASE_PATH ?? null);

$settingsValues = [
    'HOTEL_NAME'           => isset($HOTEL_NAME) ? (string)$HOTEL_NAME : '',
    'HOTEL_URL'            => isset($HOTEL_URL) ? (string)$HOTEL_URL : '',
    'BOT_NAME'             => isset($BOT_NAME) ? (string)$BOT_NAME : 'Max',
    'LOGO_PATH'            => isset($LOGO_PATH) ? (string)$LOGO_PATH : '',
    'BACKGROUND_IMAGE_URL' => isset($BACKGROUND_IMAGE_URL) ? (string)$BACKGROUND_IMAGE_URL : '',
    'PROMPT_EXTRA'         => isset($PROMPT_EXTRA) ? (string)$PROMPT_EXTRA : '',
];

$themeDefaults = [
    'THEME_COLOR_BASE'             => '#F0F0F0',
    'THEME_COLOR_SURFACE'          => '#FFFFFF',
    'THEME_COLOR_PRIMARY'          => '#003366',
    'THEME_COLOR_PRIMARY_CONTRAST' => '#FFFFFF',
    'THEME_COLOR_TEXT'             => '#0F172A',
];

foreach ($themeDefaults as $themeKey => $defaultValue) {
    $primaryValue = isset(${$themeKey}) ? (string)${$themeKey} : null;
    $cssFallback = $cssThemeColors[$themeKey] ?? null;
    $settingsValues[$themeKey] = admin_resolve_theme_color($primaryValue, $defaultValue, $cssFallback);
    ${$themeKey} = $settingsValues[$themeKey];
}

$themeSettingKeys = array_keys($themeDefaults);
$themeSettingLabels = [
    'THEME_COLOR_BASE'             => 'Grundfarbe (Base)',
    'THEME_COLOR_SURFACE'          => 'Flächenfarbe (Surface)',
    'THEME_COLOR_PRIMARY'          => 'Primärfarbe',
    'THEME_COLOR_PRIMARY_CONTRAST' => 'Kontrastfarbe (Primär)',
    'THEME_COLOR_TEXT'             => 'Textfarbe',
];
$settingsErrors = [];

$accountsFormData = [];
foreach ($availableAccounts as $account) {
    $accountsFormData[] = [
        'username' => $account['username'],
        'existing_hash' => $account['password_hash'],
    ];
}
if (empty($accountsFormData)) {
    $accountsFormData[] = ['username' => '', 'existing_hash' => ''];
}

$analysisFiltersNormalized = analytics_normalize_filters($_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_faq') {
        $activeTab = 'faq';
        $newContent = (string)($_POST['content'] ?? '');

        if (!$faqPath) {
            $faqError = 'FAQ-Datei ist in der Konfiguration nicht definiert.';
        } else {
            $dir = dirname($faqPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (@file_put_contents($faqPath, $newContent) === false) {
                $faqError = 'FAQ konnte nicht gespeichert werden. Prüfen Sie die Schreibrechte.';
            } else {
                $_SESSION['admin_flash'][] = [
                    'tab' => 'faq',
                    'type' => 'success',
                    'text' => 'FAQ wurde erfolgreich gespeichert.',
                ];
                header('Location: admin.php?tab=faq');
                exit;
            }
        }

        $faqContent = $newContent;
    }

    if ($action === 'save_settings') {
        $activeTab = 'settings';
        $postedSettings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
        $assetUploadMap = [
            'logo_upload' => [
                'config_key' => 'LOGO_PATH',
                'label' => 'Logo',
            ],
            'background_upload' => [
                'config_key' => 'BACKGROUND_IMAGE_URL',
                'label' => 'Hintergrundbild',
            ],
        ];
        $assetManagedKeys = array_map(
            static fn(array $meta) => (string)($meta['config_key'] ?? ''),
            $assetUploadMap
        );

        $newSettings = [];
        foreach ($settingsValues as $key => $current) {
            if (array_key_exists($key, $postedSettings)) {
                $value = trim((string)$postedSettings[$key]);
            } elseif (in_array($key, $assetManagedKeys, true)) {
                $value = $current;
            } else {
                $value = '';
            }

            if (in_array($key, $themeSettingKeys, true)) {
                $normalized = admin_normalize_hex_color($value);
                if ($normalized === null) {
                    $label = $themeSettingLabels[$key] ?? $key;
                    $settingsErrors[] = 'Ungültiger Farbwert für ' . $label . '.';
                    $value = $current;
                } else {
                    $value = $normalized;
                }
            }

            $settingsValues[$key] = $value;
            $newSettings[$key] = $value;
        }

        $uploadBasePath = isset($HOTEL_BASE_PATH) && is_string($HOTEL_BASE_PATH) ? $HOTEL_BASE_PATH : null;
        foreach ($assetUploadMap as $fieldName => $meta) {
            if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
                continue;
            }

            $uploadedPath = admin_handle_asset_upload(
                $_FILES[$fieldName],
                $uploadBasePath,
                'assets/uploads',
                (string)$meta['label'],
                $settingsErrors
            );

            if ($uploadedPath !== null) {
                $configKey = (string)$meta['config_key'];
                $settingsValues[$configKey] = $uploadedPath;
                $newSettings[$configKey] = $uploadedPath;
            }
        }

        $accountsPost = isset($_POST['accounts']) && is_array($_POST['accounts']) ? $_POST['accounts'] : [];
        $accountsFormData = [];
        $normalizedAccounts = [];
        $seenUsernames = [];

        foreach ($accountsPost as $row) {
            if (!is_array($row)) {
                continue;
            }
            $username = isset($row['username']) ? trim((string)$row['username']) : '';
            $password = isset($row['password']) ? (string)$row['password'] : '';
            $existingHash = isset($row['existing_hash']) ? (string)$row['existing_hash'] : '';
            $remove = !empty($row['remove']);

            $accountsFormData[] = [
                'username' => $username,
                'existing_hash' => $existingHash,
            ];

            if ($remove) {
                continue;
            }

            if ($username === '') {
                $settingsErrors[] = 'Benutzername darf nicht leer sein.';
                continue;
            }

            $usernameKey = strtolower($username);
            if (isset($seenUsernames[$usernameKey])) {
                $settingsErrors[] = 'Benutzername "' . $username . '" ist mehrfach vorhanden.';
                continue;
            }
            $seenUsernames[$usernameKey] = true;

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
            } else {
                $hash = $existingHash;
            }

            if ($hash === '') {
                $settingsErrors[] = 'Für Benutzer "' . $username . '" muss ein Passwort vergeben werden.';
                continue;
            }

            $normalizedAccounts[] = [
                'username' => $username,
                'password_hash' => $hash,
            ];
        }

        if (empty($normalizedAccounts)) {
            $settingsErrors[] = 'Mindestens ein Admin-Benutzer ist erforderlich.';
        }

        $configErrors = [];
        if (empty($settingsErrors)) {
            if (!admin_update_config_values($HOTEL_CONFIG_PATH ?? null, $newSettings, $configErrors)) {
                $settingsErrors = array_merge($settingsErrors, $configErrors);
            } else {
                foreach ($newSettings as $key => $value) {
                    $$key = $value;
                }
            }
        }

        if (empty($settingsErrors)) {
            $saveError = null;
            if (!admin_save_accounts($accountsFile, $normalizedAccounts, $saveError)) {
                $settingsErrors[] = $saveError ?: 'Admin-Benutzer konnten nicht gespeichert werden.';
            } else {
                $_SESSION['admin_flash'][] = [
                    'tab' => 'settings',
                    'type' => 'success',
                    'text' => 'Einstellungen wurden gespeichert.',
                ];
                header('Location: admin.php?tab=settings');
                exit;
            }
        }

        if (!empty($settingsErrors) && empty($accountsFormData)) {
            $accountsFormData[] = ['username' => '', 'existing_hash' => ''];
        }
    }
}

$analysisFiltersQuery = analytics_query_string($analysisFiltersNormalized);
if ($activeTab === 'analysis' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    analytics_export_csv($db ?? null, $analysisFiltersNormalized);
}
$analysisData = analytics_fetch_data($db ?? null, $analysisFiltersNormalized);
$analysisFiltersForView = $analysisFiltersNormalized;
$analysisFiltersForView['tab'] = 'analysis';
$analysisExportUrl = 'admin.php?' . ($analysisFiltersQuery ? $analysisFiltersQuery . '&' : '') . 'tab=analysis&export=csv';
$analysisTabUrl = 'admin.php?tab=analysis' . ($analysisFiltersQuery ? '&' . $analysisFiltersQuery : '');

$faqFlash = admin_filter_flash($flashMessages, 'faq');
$settingsFlash = admin_filter_flash($flashMessages, 'settings');
$logoPreviewUrl = chatbot_asset_url($settingsValues['LOGO_PATH'] ?? null, $HOTEL_BASE_PATH ?? null);
$backgroundPreviewUrl = chatbot_asset_url($settingsValues['BACKGROUND_IMAGE_URL'] ?? null, $HOTEL_BASE_PATH ?? null);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Bereich</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
</head>
<body class="admin-dashboard">
  <div class="admin-shell">
    <header class="admin-header">
      <div>
        <h1>Admin – <?php echo htmlspecialchars($HOTEL_NAME ?? ''); ?></h1>
        <p class="muted">Verwalten Sie Inhalte, Auswertungen und Zugänge für <?php echo htmlspecialchars($HOTEL_NAME ?? 'Ihr Hotel'); ?>.</p>
      </div>
      <div class="header-actions">
        <a class="button ghost" href="logout.php">Logout</a>
      </div>
    </header>

    <nav class="tab-nav">
      <a class="tab-link <?php echo $activeTab === 'analysis' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($analysisTabUrl); ?>">Analyse</a>
      <a class="tab-link <?php echo $activeTab === 'faq' ? 'active' : ''; ?>" href="?tab=faq">FAQ</a>
      <a class="tab-link <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">Einstellungen</a>
    </nav>

    <div class="tab-panels">
      <section class="tab-panel <?php echo $activeTab === 'analysis' ? 'active' : ''; ?>" id="panel-analysis">
        <?php
          $analysisFilters = $analysisFiltersForView;
          $exportUrl = $analysisExportUrl;
          $backLink = null;
          include __DIR__ . '/partials/analysis_content.php';
        ?>
      </section>

      <section class="tab-panel <?php echo $activeTab === 'faq' ? 'active' : ''; ?>" id="panel-faq">
        <div class="card">
          <h2>FAQ bearbeiten</h2>
          <?php foreach ($faqFlash as $message): ?>
            <div class="flash <?php echo htmlspecialchars($message['type'] ?? 'info'); ?>"><?php echo htmlspecialchars($message['text'] ?? ''); ?></div>
          <?php endforeach; ?>
          <?php if ($faqError): ?>
            <div class="flash error"><?php echo htmlspecialchars($faqError); ?></div>
          <?php endif; ?>
          <form method="post" class="stacked-form">
            <input type="hidden" name="action" value="save_faq">
            <textarea name="content" rows="20" spellcheck="false"><?php echo htmlspecialchars($faqContent); ?></textarea>
            <div class="form-actions">
              <button type="submit" class="primary">FAQ speichern</button>
            </div>
          </form>
        </div>
      </section>

      <section class="tab-panel <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="panel-settings">
        <form method="post" class="stacked-form settings-form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="save_settings">
          <div class="card">
            <h2>Allgemeine Einstellungen</h2>
            <?php foreach ($settingsFlash as $message): ?>
              <div class="flash <?php echo htmlspecialchars($message['type'] ?? 'info'); ?>"><?php echo htmlspecialchars($message['text'] ?? ''); ?></div>
            <?php endforeach; ?>
            <?php if (!empty($settingsErrors)): ?>
              <div class="flash error">
                <ul>
                  <?php foreach ($settingsErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
            <div class="grid two-cols">
              <label class="field">
                <span>Hotelname</span>
                <input type="text" name="settings[HOTEL_NAME]" value="<?php echo htmlspecialchars($settingsValues['HOTEL_NAME']); ?>" required>
              </label>
              <label class="field">
                <span>Hotel-Website</span>
                <input type="url" name="settings[HOTEL_URL]" value="<?php echo htmlspecialchars($settingsValues['HOTEL_URL']); ?>" placeholder="https://...">
              </label>
              <label class="field">
                <span>Bot-Name</span>
                <input type="text" name="settings[BOT_NAME]" value="<?php echo htmlspecialchars($settingsValues['BOT_NAME']); ?>">
              </label>
              <label class="field">
                <span>Logo-Datei hochladen</span>
                <?php if ($logoPreviewUrl): ?>
                  <div class="asset-preview asset-preview--logo" style="margin-bottom: 0.5rem;">
                    <img src="<?php echo htmlspecialchars($logoPreviewUrl); ?>" alt="Aktuelles Logo" style="max-height: 80px; max-width: 200px; object-fit: contain;">
                  </div>
                <?php endif; ?>
                <input type="file" name="logo_upload" accept="image/*">
                <small class="muted">Unterstützte Formate: PNG, JPG, GIF, WebP, SVG. Bereits hochgeladene Logos bleiben erhalten, solange kein neues Bild ausgewählt wird.</small>
              </label>
            </div>
            <label class="field">
              <span>Zusätzliche Prompt-Anweisungen</span>
              <textarea name="settings[PROMPT_EXTRA]" spellcheck="false"><?php echo htmlspecialchars($settingsValues['PROMPT_EXTRA']); ?></textarea>
            </label>
          </div>

          <div class="card">
            <h2>Design &amp; Branding</h2>
            <p class="muted">Steuern Sie Hintergrundbild und Farbwerte. Verwenden Sie den Upload, um ein neues Bild festzulegen.</p>
            <div class="grid two-cols">
              <label class="field" style="grid-column: span 1;">
                <span>Hintergrund-Datei hochladen</span>
                <?php if ($backgroundPreviewUrl): ?>
                  <div class="asset-preview asset-preview--background" style="margin-bottom: 0.5rem; border: 1px solid rgba(15, 23, 42, 0.1); border-radius: 0.5rem; width: 100%; max-width: 260px; height: 120px; background: #f8fafc center/cover no-repeat url('<?php echo htmlspecialchars($backgroundPreviewUrl, ENT_QUOTES); ?>');"></div>
                <?php endif; ?>
                <input type="file" name="background_upload" accept="image/*">
                <small class="muted">Unterstützte Formate: PNG, JPG, GIF, WebP, SVG. Bereits hochgeladene Hintergründe bleiben erhalten, solange kein neues Bild ausgewählt wird.</small>
              </label>
              <label class="field">
                <span>Grundfarbe (Base)</span>
                <input type="color" name="settings[THEME_COLOR_BASE]" value="<?php echo htmlspecialchars($settingsValues['THEME_COLOR_BASE']); ?>">
              </label>
              <label class="field">
                <span>Flächenfarbe (Surface)</span>
                <input type="color" name="settings[THEME_COLOR_SURFACE]" value="<?php echo htmlspecialchars($settingsValues['THEME_COLOR_SURFACE']); ?>">
              </label>
              <label class="field">
                <span>Primärfarbe</span>
                <input type="color" name="settings[THEME_COLOR_PRIMARY]" value="<?php echo htmlspecialchars($settingsValues['THEME_COLOR_PRIMARY']); ?>">
              </label>
              <label class="field">
                <span>Kontrastfarbe (Primär)</span>
                <input type="color" name="settings[THEME_COLOR_PRIMARY_CONTRAST]" value="<?php echo htmlspecialchars($settingsValues['THEME_COLOR_PRIMARY_CONTRAST']); ?>">
              </label>
              <label class="field">
                <span>Textfarbe</span>
                <input type="color" name="settings[THEME_COLOR_TEXT]" value="<?php echo htmlspecialchars($settingsValues['THEME_COLOR_TEXT']); ?>">
              </label>
            </div>
          </div>

          <div class="card">
            <h2>Admin-Benutzer</h2>
            <p class="muted">Vergeben Sie individuelle Zugänge. Lassen Sie das Passwortfeld leer, wenn es unverändert bleiben soll.</p>
            <div class="account-list" data-next-index="<?php echo count($accountsFormData); ?>">
              <?php foreach ($accountsFormData as $idx => $account): ?>
                <div class="account-row" data-account-row>
                  <label class="field">
                    <span>Benutzername</span>
                    <input type="text" name="accounts[<?php echo $idx; ?>][username]" value="<?php echo htmlspecialchars($account['username']); ?>">
                  </label>
                  <label class="field">
                    <span>Neues Passwort</span>
                    <input type="password" name="accounts[<?php echo $idx; ?>][password]" autocomplete="new-password" placeholder="Nur ausfüllen, wenn ändern">
                  </label>
                  <label class="field checkbox">
                    <input type="checkbox" name="accounts[<?php echo $idx; ?>][remove]" value="1">
                    <span>Entfernen</span>
                  </label>
                  <input type="hidden" name="accounts[<?php echo $idx; ?>][existing_hash]" value="<?php echo htmlspecialchars($account['existing_hash']); ?>">
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="button ghost add-account" data-add-account>+ Benutzer hinzufügen</button>
          </div>

          <div class="form-actions">
            <button type="submit" class="primary">Einstellungen speichern</button>
          </div>
        </form>
      </section>
    </div>
  </div>

  <template id="account-template">
    <div class="account-row" data-account-row>
      <label class="field">
        <span>Benutzername</span>
        <input type="text" name="accounts[__INDEX__][username]" value="">
      </label>
      <label class="field">
        <span>Passwort</span>
        <input type="password" name="accounts[__INDEX__][password]" autocomplete="new-password" placeholder="Neues Passwort">
      </label>
      <label class="field checkbox">
        <input type="checkbox" name="accounts[__INDEX__][remove]" value="1">
        <span>Entfernen</span>
      </label>
      <input type="hidden" name="accounts[__INDEX__][existing_hash]" value="">
    </div>
  </template>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const addButton = document.querySelector('[data-add-account]');
      const list = document.querySelector('.account-list');
      const template = document.getElementById('account-template');
      if (!addButton || !list || !template) {
        return;
      }

      addButton.addEventListener('click', function () {
        const nextIndex = parseInt(list.getAttribute('data-next-index') || '0', 10);
        const tplContent = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = tplContent.trim();
        const node = wrapper.firstElementChild;
        if (node) {
          list.appendChild(node);
        }
        list.setAttribute('data-next-index', String(nextIndex + 1));
      });
    });
  </script>
</body>
</html>
