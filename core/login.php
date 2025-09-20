<?php
/**
 * Anmeldeseite für den Administrationsbereich.
 */

session_start();

// hotelspezifische Konfiguration laden
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/admin_accounts.php';

// Relativer Pfad zum Core‑Verzeichnis. Wird vom Hotel‑Wrapper gesetzt. Fallback: '.'
$coreRelative = $coreRelative ?? '.';

// Session-Key für den Admin-Zugang bestimmen
$adminSessionKey = $ADMIN_SESSION_KEY ?? 'admin';

// Prüfen, ob bereits eine gültige Admin-Sitzung besteht
$existingAuth = $_SESSION[$adminSessionKey] ?? null;
$alreadyLoggedIn = is_array($existingAuth)
    ? (!empty($existingAuth['authenticated']))
    : ($existingAuth === true);
if ($alreadyLoggedIn) {
    header('Location: admin.php');
    exit;
}

$accountsFile = admin_accounts_file_path($HOTEL_BASE_PATH ?? null);
$availableAccounts = admin_load_accounts(
    $accountsFile,
    $ADMIN_USERS ?? null,
    $ADMIN_USER ?? null,
    $ADMIN_PASSWORD_HASH ?? null
);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $authenticated = false;

    foreach ($availableAccounts as $account) {
        $storedUser = $account['username'];
        $storedHash = $account['password_hash'];

        if ($storedUser === '' || $storedHash === '') {
            continue;
        }

        if (hash_equals($storedUser, $username) && password_verify($password, $storedHash)) {
            $authenticated = true;
            $selectedUser = $storedUser;
            break;
        }
    }

    if ($authenticated) {
        session_regenerate_id(true);
        $_SESSION[$adminSessionKey] = [
            'authenticated' => true,
            'username' => $selectedUser ?? $username,
            'login_time' => time(),
        ];
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Benutzername oder Passwort ist ungültig.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <!-- Gemeinsames Stylesheet aus dem Core einbinden -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars($coreRelative); ?>/assets/css/style.css">
</head>

<body class="admin-dashboard admin-login-page">
    <div class="auth-shell">
        <div class="card auth-card">
            <h1>Admin Login</h1>
            <?php if (!empty($HOTEL_NAME)): ?>
                <p class="muted">Melden Sie sich an, um <?php echo htmlspecialchars($HOTEL_NAME); ?> zu verwalten.</p>
            <?php else: ?>
                <p class="muted">Bitte melden Sie sich mit Ihren Zugangsdaten an.</p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" class="stacked-form">
                <label class="field">
                    <span>Benutzername</span>
                    <input type="text" name="username" autocomplete="username" required autofocus>
                </label>
                <label class="field">
                    <span>Passwort</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <div class="form-actions">
                    <button type="submit" class="primary">Anmelden</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

