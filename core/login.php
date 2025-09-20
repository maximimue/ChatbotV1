<?php
/**
 * Anmeldeseite für den Administrationsbereich.
 */

session_start();

// hotelspezifische Konfiguration laden
require_once __DIR__ . '/init.php';

// Relativer Pfad zum Core‑Verzeichnis. Wird vom Hotel‑Wrapper gesetzt. Fallback: '.'
$coreRelative = $coreRelative ?? '.';

// Wenn bereits eingeloggt, zur Admin‑Übersicht weiterleiten
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username === $ADMIN_USER && password_verify($password, $ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin'] = true;
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
            <input type="text" name="username" required>
            <label>Passwort</label>
            <input type="password" name="password" required>
            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>