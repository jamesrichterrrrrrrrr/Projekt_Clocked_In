<?php
/**
 * Kopiere diese Datei nach config.php und trage die Werte aus dem Hosting-Manager ein.
 *
 * Infomaniak: Hosting → [dein Webhosting] → Datenbanken → Konfiguration
 *   „MySQL/MariaDB-Server“ / Hostname (z. B. XXXX.myd.infomaniak.com) als DB_HOST verwenden,
 *   wenn localhost nicht funktioniert.
 *
 * Optional statt Datei: in .htaccess (Apache) z. B.:
 *   SetEnv DB_HOST "XXXX.myd.infomaniak.com"
 *   SetEnv DB_NAME "deine_datenbank"
 *   SetEnv DB_USER "dein_user"
 *   SetEnv DB_PASS "dein_passwort"
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '';
$db   = getenv('DB_NAME') ?: 'DEINE_DATENBANK';
$user = getenv('DB_USER') ?: 'DEIN_MYSQL_USER';

$envPass = getenv('DB_PASS');
$pass = ($envPass !== false && $envPass !== '') ? $envPass : 'DEIN_PASSWORT';

$dsn = 'mysql:host=' . $host . ';charset=utf8mb4';
if ($port !== '' && $port !== false) {
    $dsn .= ';port=' . (int) $port;
}
$dsn .= ';dbname=' . $db;

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
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
