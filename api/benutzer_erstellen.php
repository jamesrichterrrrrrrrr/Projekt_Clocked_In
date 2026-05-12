<?php
declare(strict_types=1);

/**
 * Neuen Benutzer anlegen (multipart/form-data, wie newuser.html).
 * Liegt unter api/ wie login.php / register.php — gleiche Pfade auf dem Server.
 */
ob_start();

if (!function_exists('clocked_json_exit')) {
    /**
     * @param array<string, mixed> $payload
     */
    function clocked_json_exit(array $payload, int $code = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

require_once __DIR__ . '/../system/cors.php';
require_once __DIR__ . '/../system/config.php';

const LOCATION_MAP = [
    'Chur'   => 1,
    'Bern'   => 2,
    'Zürich' => 3,
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clocked_json_exit(['success' => false, 'message' => 'Methode nicht erlaubt.'], 405);
}

$firstname = trim($_POST['firstname'] ?? '');
$lastname  = trim($_POST['lastname']  ?? '');
$rolle     = trim($_POST['rolle']     ?? '');
$email     = trim($_POST['email']     ?? '');
$passwort  = $_POST['passwort']       ?? '';
$ort       = trim($_POST['ort']       ?? '');
$card_id   = trim($_POST['card_id']   ?? '');

$erlaubteRollen = ['Admin', 'Ausleihe'];
$erlaubteOrte   = array_keys(LOCATION_MAP);

$fehler = [];

if (empty($firstname)) {
    $fehler[] = 'Vorname fehlt.';
}
if (empty($lastname)) {
    $fehler[] = 'Nachname fehlt.';
}
if (!in_array($rolle, $erlaubteRollen, true)) {
    $fehler[] = 'Ungültige Rolle.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $fehler[] = 'Ungültige E-Mail-Adresse.';
}
if (strlen($passwort) < 8) {
    $fehler[] = 'Passwort muss mindestens 8 Zeichen haben.';
}
if (!in_array($ort, $erlaubteOrte, true)) {
    $fehler[] = 'Ungültiger Ort.';
}
if (empty($card_id)) {
    $fehler[] = 'Karten-ID fehlt.';
}

if (!empty($fehler)) {
    clocked_json_exit(['success' => false, 'message' => implode(' ', $fehler)], 422);
}

$location_id = LOCATION_MAP[$ort];
$app_role    = ($rolle === 'Admin') ? 'admin' : 'user';
$job_title   = $rolle;

$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$hash = password_hash($passwort, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password, firstname, lastname, app_role, job_title, location_id, card_id)
         VALUES (:email, :password, :firstname, :lastname, :app_role, :job_title, :location_id, :card_id)"
    );
    $stmt->execute([
        ':email'       => $email,
        ':password'    => $hash,
        ':firstname'   => $firstname,
        ':lastname'    => $lastname,
        ':app_role'    => $app_role,
        ':job_title'   => $job_title,
        ':location_id' => $location_id,
        ':card_id'     => $card_id,
    ]);

    $msg = 'Benutzer „' . $firstname . ' ' . $lastname . '“ wurde erfolgreich erstellt.';

    clocked_json_exit([
        'success' => true,
        'message' => $msg,
        'id'      => (int) $pdo->lastInsertId(),
    ], 200);
} catch (PDOException $e) {
    $sqlState = $e->errorInfo[0] ?? '';
    if ($sqlState === '23000') {
        clocked_json_exit(['success' => false, 'message' => 'Diese E-Mail-Adresse oder Karten-ID ist bereits vergeben.'], 409);
    }
    clocked_json_exit(['success' => false, 'message' => 'Datenbankfehler beim Speichern.'], 500);
}
