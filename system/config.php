<?php
/**
 * Datenbank-Verbindung
 *
 * Werte im Infomaniak Manager unter Hosting → Datenbanken:
 *   Server / Hostname (z. B. XXXX.myd.infomaniak.com) — oft NICHT „localhost“
 *   Datenbankname, Benutzername, Passwort
 *
 * Optional per Umgebungsvariable (z. B. in .htaccess mit SetEnv), ohne PHP zu ändern:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT
 *
 * Hinweis: Webspace und MySQL müssen zusammenpassen (z. B. beides Infomaniak dasselbe Konto).
 * Ein interner Host wie *.mysql.db.internal funktioniert nur von Infomaniak-Webspace aus,
 * nicht von einem anderen Anbieter mit localhost.
 * Falls Verbindung scheitert: im Manager den angezeigten Server (z. B. …myd.infomaniak.com) als DB_HOST setzen.
 */

$host = getenv('DB_HOST') ?: 'epakubix.mysql.db.internal';
$port = getenv('DB_PORT') ?: '';
$db   = getenv('DB_NAME') ?: 'epakubix_im4';
$user = getenv('DB_USER') ?: 'epakubix_im4';
$pass = getenv('DB_PASS') !== false && getenv('DB_PASS') !== ''
    ? (string) getenv('DB_PASS')
    : 'ePuNr*9dK-6jQ86wbRUU';

$dsn = 'mysql:host=' . $host . ';charset=utf8mb4';
if ($port !== '' && $port !== false) {
    $dsn .= ';port=' . (int) $port;
}
$dsn .= ';dbname=' . $db;

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    // Immer ins Server-Log (Manager → Logs / error_log), nie die echte Meldung an den Browser
    error_log('ClockedIn DB connection failed: ' . $e->getMessage());

    if (php_sapi_name() !== 'cli' && !headers_sent()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Datenbankverbindung fehlgeschlagen.',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
