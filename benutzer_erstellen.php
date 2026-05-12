<?php
// ─────────────────────────────────────────────
//  Konfiguration – bitte anpassen
// ─────────────────────────────────────────────
define('DB_HOST', '225f75.myd.infomaniak.com');
define('DB_NAME', '225f75_clocked_in');
define('DB_USER', '225f75_quake_it');
define('DB_PASS', 'githeh-wurxi3-Vaxxep');// ← eigenes DB-Passwort eintragen
define('DB_CHARSET', 'utf8mb4');

// location_id Mapping: Ort → ID
const LOCATION_MAP = [
    'Chur'   => 1,
    'Bern'   => 2,
    'Zürich' => 3,
];

// ─────────────────────────────────────────────
//  Nur POST-Anfragen akzeptieren
// ─────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode nicht erlaubt.']);
    exit;
}

// ─────────────────────────────────────────────
//  Eingaben bereinigen & validieren
// ─────────────────────────────────────────────
$firstname = trim($_POST['firstname'] ?? '');
$lastname  = trim($_POST['lastname']  ?? '');
$rolle     = trim($_POST['rolle']     ?? '');   // Admin | Ausleihe
$email     = trim($_POST['email']     ?? '');
$passwort  = $_POST['passwort']       ?? '';
$ort       = trim($_POST['ort']       ?? '');
$card_id   = trim($_POST['card_id']   ?? '');

$erlaubteRollen = ['Admin', 'Ausleihe'];
$erlaubteOrte   = array_keys(LOCATION_MAP);

$fehler = [];

if (empty($firstname))                              $fehler[] = 'Vorname fehlt.';
if (empty($lastname))                               $fehler[] = 'Nachname fehlt.';
if (!in_array($rolle, $erlaubteRollen, true))       $fehler[] = 'Ungültige Rolle.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $fehler[] = 'Ungültige E-Mail-Adresse.';
if (strlen($passwort) < 8)                          $fehler[] = 'Passwort muss mindestens 8 Zeichen haben.';
if (!in_array($ort, $erlaubteOrte, true))           $fehler[] = 'Ungültiger Ort.';
if (empty($card_id))                                $fehler[] = 'Karten-ID fehlt.';

if (!empty($fehler)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $fehler)]);
    exit;
}

// ─────────────────────────────────────────────
//  Abgeleitete Werte
// ─────────────────────────────────────────────
$location_id = LOCATION_MAP[$ort];
// app_role: 'admin' für Admin, 'user' für Ausleihe
$app_role    = ($rolle === 'Admin') ? 'admin' : 'user';
// job_title: die schöne Bezeichnung
$job_title   = $rolle;   // "Admin" oder "Ausleihe"

// ─────────────────────────────────────────────
//  Datenbankverbindung
// ─────────────────────────────────────────────
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$optionen = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $optionen);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen.']);
    exit;
}

// ─────────────────────────────────────────────
//  Passwort hashen & Benutzer einfügen
// ─────────────────────────────────────────────
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

    echo json_encode([
        'success' => true,
        'message' => "Benutzer „{$firstname} {$lastname}" wurde erfolgreich erstellt.",
        'id'      => (int) $pdo->lastInsertId(),
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Diese E-Mail-Adresse oder Karten-ID ist bereits vergeben.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Datenbankfehler beim Speichern.']);
    }
}
