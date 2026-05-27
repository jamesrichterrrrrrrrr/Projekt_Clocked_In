<?php
declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/config.php';

const CLOCKED_LOCATION_NAMES = [
    1 => 'Chur',
    2 => 'Bern',
    3 => 'Zürich',
];

function clocked_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.cookie_httponly', '1');
    clocked_apply_cross_origin_session_cookie();
    session_start();
}

/**
 * @param array<string, mixed> $payload
 */
function clocked_json(array $payload, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function clocked_require_user_id(): int
{
    clocked_start_session();
    if (!isset($_SESSION['user_id'])) {
        clocked_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    return (int) $_SESSION['user_id'];
}

/**
 * @return array<string, mixed>|null
 */
function clocked_fetch_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, email, firstname, lastname, app_role, job_title, location_id, card_id
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function clocked_user_payload(array $user): array
{
    $locationId = isset($user['location_id']) ? (int) $user['location_id'] : 0;
    $locationName = CLOCKED_LOCATION_NAMES[$locationId] ?? 'Unbekannt';

    return [
        'id'            => (int) $user['id'],
        'email'         => $user['email'],
        'firstname'     => $user['firstname'] ?? '',
        'lastname'      => $user['lastname'] ?? '',
        'display_name'  => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        'app_role'      => $user['app_role'] ?? 'user',
        'job_title'     => $user['job_title'] ?? '',
        'location_id'   => $locationId ?: null,
        'location_name' => $locationName,
        'card_id'       => $user['card_id'] ?? '',
    ];
}

function clocked_mitarbeiter_name(array $user): string
{
    $name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    return $name !== '' ? $name : (string) ($user['email'] ?? 'Web');
}

function clocked_user_uid(array $user): string
{
    $card = trim((string) ($user['card_id'] ?? ''));
    if ($card !== '') {
        return $card;
    }
    return 'web-' . (int) $user['id'];
}

function clocked_normalize_aktion(?string $aktion): string
{
    return trim((string) $aktion);
}

function clocked_is_check_in(?string $aktion): bool
{
    $a = strtolower(clocked_normalize_aktion($aktion));
    return $a === 'check-in' || $a === 'check in' || $a === 'checkin';
}

function clocked_is_check_out(?string $aktion): bool
{
    $a = strtolower(clocked_normalize_aktion($aktion));
    return $a === 'check-out' || $a === 'check out' || $a === 'checkout';
}

/** @return array<string, mixed>|null */
function clocked_find_open_session(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT ci.id, ci.zeitstempel, ci.aktion, ci.dauer_sekunden
         FROM arbeitszeiten ci
         WHERE ci.user_id = :uid
           AND ci.aktion LIKE 'Check-In%'
           AND NOT EXISTS (
             SELECT 1 FROM arbeitszeiten co
             WHERE co.user_id = ci.user_id
               AND co.aktion LIKE 'Check-Out%'
               AND co.zeitstempel > ci.zeitstempel
           )
         ORDER BY ci.zeitstempel DESC, ci.id DESC
         LIMIT 1"
    );
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function clocked_normalize_hm(string $time): ?string
{
    if (preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
    return null;
}
