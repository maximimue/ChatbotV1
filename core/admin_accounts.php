<?php
/**
 * Hilfsfunktionen zum Laden und Speichern der Admin-Benutzer pro Hotel.
 */

/**
 * Liefert den Pfad zur Admin-Benutzerdatei (JSON) relativ zur Hotelbasis.
 */
function admin_accounts_file_path(?string $hotelBasePath): string
{
    $base = $hotelBasePath ?: __DIR__;
    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'admin_users.json';
}

/**
 * Normalisiert ein Benutzer-Array und gibt nur gültige Einträge zurück.
 *
 * @param array<int,array<string,mixed>> $accounts
 * @return array<int,array{username:string,password_hash:string}>
 */
function admin_normalize_accounts(array $accounts): array
{
    $normalized = [];
    foreach ($accounts as $account) {
        if (!is_array($account)) {
            continue;
        }
        $username = isset($account['username']) ? trim((string)$account['username']) : '';
        $hash = isset($account['password_hash']) ? (string)$account['password_hash'] : '';
        if ($username === '' || $hash === '') {
            continue;
        }
        $normalized[] = [
            'username' => $username,
            'password_hash' => $hash,
        ];
    }
    return $normalized;
}

/**
 * Lädt alle verfügbaren Admin-Benutzer aus JSON-Datei oder Konfiguration.
 *
 * Reihenfolge der Quellen:
 *  1. JSON-Datei (falls vorhanden)
 *  2. $ADMIN_USERS Array aus der Konfiguration
 *  3. Einzelner Benutzer aus $ADMIN_USER / $ADMIN_PASSWORD_HASH
 *
 * @param string|null $accountsFile
 * @param mixed $configUsers
 * @param mixed $configUser
 * @param mixed $configHash
 * @return array<int,array{username:string,password_hash:string}>
 */
function admin_load_accounts(?string $accountsFile, $configUsers, $configUser, $configHash): array
{
    // 1) JSON-Datei bevorzugen
    if ($accountsFile && is_readable($accountsFile)) {
        $json = file_get_contents($accountsFile);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $normalized = admin_normalize_accounts($data);
                if (!empty($normalized)) {
                    return $normalized;
                }
            }
        }
    }

    // 2) $ADMIN_USERS aus der Konfiguration
    if (is_array($configUsers)) {
        $normalized = [];
        foreach ($configUsers as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $username = isset($entry['username']) ? trim((string)$entry['username']) : '';
            $hash = isset($entry['password_hash']) ? (string)$entry['password_hash'] : '';
            if ($username === '' || $hash === '') {
                continue;
            }
            $normalized[] = [
                'username' => $username,
                'password_hash' => $hash,
            ];
        }
        if (!empty($normalized)) {
            return $normalized;
        }
    }

    // 3) Fallback auf Einzelbenutzer
    $user = is_string($configUser) ? trim($configUser) : '';
    $hash = is_string($configHash) ? $configHash : '';
    if ($user !== '' && $hash !== '') {
        return [[
            'username' => $user,
            'password_hash' => $hash,
        ]];
    }

    return [];
}

/**
 * Speichert Admin-Benutzer als JSON-Datei.
 *
 * @param string $accountsFile
 * @param array<int,array{username:string,password_hash:string}> $accounts
 * @param string|null $error
 * @return bool
 */
function admin_save_accounts(string $accountsFile, array $accounts, ?string &$error = null): bool
{
    $dir = dirname($accountsFile);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $error = 'Verzeichnis für Admin-Benutzer konnte nicht erstellt werden.';
            return false;
        }
    }

    $json = json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $error = 'Benutzer konnten nicht serialisiert werden.';
        return false;
    }

    if (@file_put_contents($accountsFile, $json) === false) {
        $error = 'Admin-Benutzerdatei konnte nicht gespeichert werden.';
        return false;
    }

    return true;
}
