<?php
/**
 * Anmeldeseite für den Administrationsbereich.
 */

session_start();

// hotelspezifische Konfiguration laden
require_once __DIR__ . '/init.php';

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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $storedUser = (isset($ADMIN_USER) && is_string($ADMIN_USER)) ? $ADMIN_USER : '';
    $storedHash = (isset($ADMIN_PASSWORD_HASH) && is_string($ADMIN_PASSWORD_HASH)) ? $ADMIN_PASSWORD_HASH : '';

    $usernameMatches = ($storedUser !== '') && hash_equals($storedUser, $username);
    $passwordMatches = ($storedHash !== '') && password_verify($password, $storedHash);

    if ($usernameMatches && $passwordMatches) {
        session_regenerate_id(true);
        $_SESSION[$adminSessionKey] = [
            'authenticated' => true,
            'username' => $storedUser,
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
    <style>
    body {
        background: #f5f5f5;
    }
    .login-wrapper {
        max-width: 400px;
        margin: 100px auto;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    .login-wrapper input {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .login-wrapper button {
        width: 100%;
        padding: 8px;
        background: #003366;
        border: none;
        border-radius: 4px;
        color: #fff;
        cursor: pointer;
    }
    .login-wrapper button:hover {
        background: #004080;
    }
    .error {
        color: red;
        margin-bottom: 10px;
    }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <label>Benutzername</label>
            <input type="text" name="username" autocomplete="username" required autofocus>
            <label>Passwort</label>
            <input type="password" name="password" autocomplete="current-password" required>
            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>