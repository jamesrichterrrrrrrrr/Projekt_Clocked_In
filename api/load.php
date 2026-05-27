<?php
declare(strict_types=1);

/**
 * ESP32 / NFC Endpoint
 *
 * Erwarteter POST JSON Body:
 *   { "card_id": "8FCA391F" }
 *
 * Kompatibel auch mit:
 *   { "uid": "8FCA391F" }
 */

require_once __DIR__ . '/../system/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Device-Key');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    clocked_json([
        'success' => false,
        'message' => 'Nur POST erlaubt.'
    ], 405);
}

$deviceKey = getenv('DEVICE_API_KEY');

if ($deviceKey !== false && $deviceKey !== '') {
    $sent = $_SERVER['HTTP_X_DEVICE_KEY'] ?? '';

    if (!hash_equals((string) $deviceKey, (string) $sent)) {
        clocked_json([
            'success' => false,
            'message' => 'Unauthorized device.'
        ], 401);
    }
}

$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data)) {
    clocked_json([
        'success' => false,
        'message' => 'Ungültiges JSON.'
    ], 400);
}

/**
 * Die externe Quelle sendet card_id.
 * uid bleibt als Fallback drin, falls dein Gerät noch uid sendet.
 */
$cardId = strtoupper(trim((string) ($data['card_id'] ?? $data['uid'] ?? '')));

if ($cardId === '') {
    clocked_json([
        'success' => false,
        'message' => 'card_id ist erforderlich.'
    ], 422);
}

/**
 * Normalisierung:
 * Entfernt Leerzeichen, Bindestriche, Doppelpunkte etc.
 * Beispiel: "8F CA 39 1F" wird zu "8FCA391F".
 */
$cardId = preg_replace('/[^A-Z0-9]/', '', $cardId) ?? '';

if ($cardId === '') {
    clocked_json([
        'success' => false,
        'message' => 'card_id ist ungültig.'
    ], 422);
}

/**
 * Mitarbeiter anhand der Karte finden.
 *
 * @return array<string, mixed>|null
 */
function load_find_user_by_card(PDO $pdo, string $cardId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, firstname, lastname, card_id
         FROM users
         WHERE UPPER(card_id) = :card_id
         LIMIT 1'
    );

    $stmt->execute([
        ':card_id' => $cardId
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Letzten Arbeitszeit-Eintrag des Mitarbeiters holen.
 *
 * @return array<string, mixed>|null
 */
function load_find_latest_time_entry(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid
         FROM arbeitszeiten
         WHERE user_id = :user_id
         ORDER BY zeitstempel DESC, id DESC
         LIMIT 1'
    );

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $entry = $stmt->fetch();

    return $entry ?: null;
}

/**
 * Schöner Anzeigename für arbeitszeiten.mitarbeiter.
 *
 * @param array<string, mixed> $user
 */
function load_display_name(array $user): string
{
    $firstname = trim((string) ($user['firstname'] ?? ''));
    $lastname  = trim((string) ($user['lastname'] ?? ''));
    $email     = trim((string) ($user['email'] ?? ''));

    $name = trim($firstname . ' ' . $lastname);

    if ($name !== '') {
        return $name;
    }

    if ($email !== '') {
        return $email;
    }

    return 'Unbekannt';
}
try {
    $user = load_find_user_by_card($pdo, $cardId);

    // --- NEU: UNBEKANNTE KARTE BEHANDELN ---
    if ($user === null) {
        // Prüfen, ob die Karte schon in der "unbekannte_karten" Tabelle ist
        $stmtCheck = $pdo->prepare('SELECT id FROM unbekannte_karten WHERE card_id = :card_id');
        $stmtCheck->execute([':card_id' => $cardId]);
        
        // Wenn nicht, fügen wir sie hinzu
        if (!$stmtCheck->fetch()) {
            $stmtInsert = $pdo->prepare('INSERT INTO unbekannte_karten (card_id) VALUES (:card_id)');
            $stmtInsert->execute([':card_id' => $cardId]);
        }

        // Wir melden an den ESP32 "Erfolg", aber mit der Aktion "Unbekannt"
        clocked_json([
            'success' => true, // true, damit der ESP32 keinen roten Fehler wirft
            'message' => 'Neue Karte gemerkt.',
            'aktion'  => 'Unbekannt',
            'mitarbeiter' => 'Neu',
            'card_id' => $cardId,
            'dauer_sekunden' => 0
        ]);
    }
    // ----------------------------------------

    $userId = (int) $user['id'];
    $displayName = load_display_name($user);

    $latestEntry = load_find_latest_time_entry($pdo, $userId);

    /**
     * Wenn der letzte Eintrag Check-In ist:
     * Der Mitarbeiter ist aktuell eingecheckt.
     * Also machen wir jetzt Check-Out.
     */
    $isCurrentlyCheckedIn = $latestEntry !== null
        && (string) $latestEntry['aktion'] === 'Check-In';

    if ($isCurrentlyCheckedIn) {
        $checkInAt = (string) $latestEntry['zeitstempel'];

        $stmt = $pdo->prepare(
            'INSERT INTO arbeitszeiten
                (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
             VALUES
                (
                    :user_id,
                    NOW(),
                    :mitarbeiter,
                    :aktion,
                    GREATEST(0, TIMESTAMPDIFF(SECOND, :check_in_at, NOW())),
                    :uid
                )'
        );

        $stmt->execute([
            ':user_id'     => $userId,
            ':mitarbeiter' => $displayName,
            ':aktion'      => 'Check-Out',
            ':check_in_at' => $checkInAt,
            ':uid'         => $cardId,
        ]);

        $durationStmt = $pdo->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, :check_in_at, NOW())) AS dauer_sekunden'
        );

        $durationStmt->execute([
            ':check_in_at' => $checkInAt
        ]);

        $durationRow = $durationStmt->fetch();
        $dauerSekunden = (int) ($durationRow['dauer_sekunden'] ?? 0);

        clocked_json([
            'success'        => true,
            'message'        => 'Check-Out gespeichert.',
            'aktion'         => 'Check-Out',
            'user_id'        => $userId,
            'mitarbeiter'    => $displayName,
            'card_id'        => $cardId,
            'dauer_sekunden' => $dauerSekunden,
            'checked_in'     => false
        ]);
    }

    /**
     * Wenn kein letzter Eintrag existiert oder letzter Eintrag Check-Out war:
     * Neuer Check-In.
     */
    $stmt = $pdo->prepare(
        'INSERT INTO arbeitszeiten
            (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
         VALUES
            (:user_id, NOW(), :mitarbeiter, :aktion, 0, :uid)'
    );

    $stmt->execute([
        ':user_id'     => $userId,
        ':mitarbeiter' => $displayName,
        ':aktion'      => 'Check-In',
        ':uid'         => $cardId,
    ]);

    clocked_json([
        'success'     => true,
        'message'     => 'Check-In gespeichert.',
        'aktion'      => 'Check-In',
        'user_id'     => $userId,
        'mitarbeiter' => $displayName,
        'card_id'     => $cardId,
        'checked_in'  => true
    ]);
} catch (PDOException $e) {
    error_log('load.php: ' . $e->getMessage());

    clocked_json([
        'success' => false,
        'message' => 'Datenbankfehler.'
    ], 500);
}