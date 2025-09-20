<?php
/**
 * Administrationsoberfläche mit Reitern für Analyse, FAQ und Einstellungen.
 */

session_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/analytics_helpers.php';
require_once __DIR__ . '/admin_accounts.php';

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
        $pattern = '/\$' . preg_quote($key, '/') . '\s*=\s*[^;]*;/m';
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, '$' . $key . ' = ' . $exported . ';', $contents, 1);
        } else {
            $contents = rtrim($contents) . PHP_EOL . '$' . $key . ' = ' . $exported . ';' . PHP_EOL;
        }
    }

    if (@file_put_contents($configPath, $contents) === false) {
        $errors[] = 'Konfigurationsdatei konnte nicht gespeichert werden.';
        return false;
    }

    return true;
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

$settingsValues = [
    'HOTEL_NAME' => isset($HOTEL_NAME) ? (string)$HOTEL_NAME : '',
    'HOTEL_URL'  => isset($HOTEL_URL) ? (string)$HOTEL_URL : '',
    'API_URL'    => isset($API_URL) ? (string)$API_URL : '',
    'BOT_NAME'   => isset($BOT_NAME) ? (string)$BOT_NAME : '',
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
        $newSettings = [];
        foreach ($settingsValues as $key => $current) {
            $value = isset($postedSettings[$key]) ? trim((string)$postedSettings[$key]) : '';
            $settingsValues[$key] = $value;
            $newSettings[$key] = $value;
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
        <form method="post" class="stacked-form settings-form">
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
                <span>API-Endpunkt</span>
                <input type="url" name="settings[API_URL]" value="<?php echo htmlspecialchars($settingsValues['API_URL']); ?>" placeholder="https://...">
              </label>
              <label class="field">
                <span>Bot-Name</span>
                <input type="text" name="settings[BOT_NAME]" value="<?php echo htmlspecialchars($settingsValues['BOT_NAME']); ?>">
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
