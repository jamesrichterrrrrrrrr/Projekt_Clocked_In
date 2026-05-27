<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/bootstrap.php';

$userId = clocked_require_user_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$user = clocked_fetch_user($pdo, $userId);
if (!$user) {
    clocked_json(['success' => false, 'message' => 'User not found'], 404);
}

$mitarbeiter = clocked_mitarbeiter_name($user);
$uid         = clocked_user_uid($user);

/**
 * @return array<string, mixed>|null
 */
function clocked_last_event(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, zeitstempel, aktion, dauer_sekunden
         FROM arbeitszeiten
         WHERE user_id = :uid
         ORDER BY zeitstempel DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function clocked_is_clocked_in(?array $last): bool
{
    return $last !== null && clocked_is_check_in($last['aktion'] ?? '');
}

function clocked_today_completed_seconds(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(dauer_sekunden), 0) AS total
         FROM arbeitszeiten
         WHERE user_id = :uid
           AND aktion = 'Check-Out'
           AND DATE(zeitstempel) = CURDATE()"
    );
    $stmt->execute([':uid' => $userId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * @return list<array<string, mixed>>
 */
function clocked_events_between(PDO $pdo, int $userId, string $fromDate, string $toDate): array
{
    $stmt = $pdo->prepare(
        'SELECT id, zeitstempel, aktion, dauer_sekunden, mitarbeiter
         FROM arbeitszeiten
         WHERE user_id = :uid
           AND DATE(zeitstempel) BETWEEN :from_d AND :to_d
         ORDER BY zeitstempel ASC, id ASC'
    );
    $stmt->execute([
        ':uid'    => $userId,
        ':from_d' => $fromDate,
        ':to_d'   => $toDate,
    ]);
    return $stmt->fetchAll();
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array<string, mixed>>
 */
function clocked_build_day_summaries(array $events): array
{
    $byDay = [];

    $addSession = static function (string $day, ?string $checkIn, ?string $checkOut, int $seconds) use (&$byDay): void {
        if ($seconds <= 0 && $checkIn === null && $checkOut === null) {
            return;
        }
        if (!isset($byDay[$day])) {
            $byDay[$day] = [
                'date'          => $day,
                'total_seconds' => 0,
                'sessions'      => [],
            ];
        }
        $byDay[$day]['total_seconds'] += $seconds;
        $byDay[$day]['sessions'][] = [
            'check_in'  => $checkIn,
            'check_out' => $checkOut,
            'seconds'   => $seconds,
        ];
    };

    for ($i = 0; $i < count($events); $i++) {
        $ev = $events[$i];
        $aktion = trim((string) ($ev['aktion'] ?? ''));

        if ($aktion === 'Check-In') {
            $day     = substr((string) $ev['zeitstempel'], 0, 10);
            $checkIn = (string) $ev['zeitstempel'];

            if (
                isset($events[$i + 1])
                && trim((string) ($events[$i + 1]['aktion'] ?? '')) === 'Check-Out'
            ) {
                $out = $events[$i + 1];
                $seconds = (int) ($out['dauer_sekunden'] ?? 0);
                if ($seconds <= 0) {
                    $inTs  = strtotime($checkIn);
                    $outTs = strtotime((string) $out['zeitstempel']);
                    if ($inTs !== false && $outTs !== false) {
                        $seconds = max(0, $outTs - $inTs);
                    }
                }
                $addSession($day, $checkIn, (string) $out['zeitstempel'], $seconds);
                $i++;
                continue;
            }

            $inTs = strtotime($checkIn);
            if ($inTs !== false) {
                $isToday = date('Y-m-d', $inTs) === date('Y-m-d');
                $endTs   = $isToday ? time() : strtotime(date('Y-m-d', $inTs) . ' 23:59:59');
                if ($endTs !== false) {
                    $addSession($day, $checkIn, null, max(0, $endTs - $inTs));
                }
            }
            continue;
        }

        if ($aktion === 'Check-Out') {
            $day     = substr((string) $ev['zeitstempel'], 0, 10);
            $seconds = (int) ($ev['dauer_sekunden'] ?? 0);
            if ($seconds <= 0) {
                continue;
            }
            $addSession($day, null, (string) $ev['zeitstempel'], $seconds);
        }
    }

    krsort($byDay);
    return array_values($byDay);
}

if ($method === 'GET') {
    $mode = $_GET['mode'] ?? '';

    if ($mode === 'status') {
        $open = clocked_find_open_session($pdo, $userId);
        $clockedIn = $open !== null;
        $todaySeconds = clocked_today_completed_seconds($pdo, $userId);

        if ($clockedIn && $open) {
            $checkInTs = strtotime((string) $open['zeitstempel']);
            if ($checkInTs !== false) {
                $todaySeconds += max(0, time() - $checkInTs);
            }
        }

        clocked_json([
            'success'        => true,
            'clocked_in'     => $clockedIn,
            'check_in_at'    => $clockedIn && $open ? $open['zeitstempel'] : null,
            'today_seconds'  => $todaySeconds,
        ]);
    }

    $from = $_GET['from'] ?? date('Y-m-d', strtotime('monday this week'));
    $to   = $_GET['to'] ?? date('Y-m-d', strtotime('sunday this week'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        clocked_json(['success' => false, 'message' => 'Invalid date range'], 400);
    }

    $events = clocked_events_between($pdo, $userId, $from, $to);
    $days = clocked_build_day_summaries($events);

    clocked_json([
        'success' => true,
        'from'    => $from,
        'to'      => $to,
        'events'  => $events,
        'days'    => $days,
    ]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        clocked_json(['success' => false, 'message' => 'Invalid JSON body'], 400);
    }

    $aktion = trim($data['aktion'] ?? '');
    if (!in_array($aktion, ['Check-In', 'Check-Out', 'Manual'], true)) {
        clocked_json(['success' => false, 'message' => 'Ungültige Aktion.'], 422);
    }

    $open    = clocked_find_open_session($pdo, $userId);
    $clockedIn = $open !== null;

    if ($aktion === 'Manual') {
        if ($clockedIn) {
            clocked_json([
                'success' => false,
                'message' => 'Bitte zuerst ausstempeln, bevor du manuell erfasst.',
            ], 409);
        }

        $von   = clocked_normalize_hm(trim($data['von'] ?? ''));
        $bis   = clocked_normalize_hm(trim($data['bis'] ?? ''));
        $datum = trim($data['datum'] ?? date('Y-m-d'));

        if ($von === null || $bis === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
            clocked_json(['success' => false, 'message' => 'Ungültige Zeiten.'], 422);
        }

        $inTs  = strtotime($datum . ' ' . $von . ':00');
        $outTs = strtotime($datum . ' ' . $bis . ':00');
        if ($inTs === false || $outTs === false) {
            clocked_json(['success' => false, 'message' => 'Ungültige Zeiten.'], 422);
        }
        if ($outTs <= $inTs) {
            clocked_json(['success' => false, 'message' => 'Ende muss nach Start liegen.'], 422);
        }

        $dauer = $outTs - $inTs;

        $stmtIn = $pdo->prepare(
            'INSERT INTO arbeitszeiten (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
             VALUES (:user_id, :zeitstempel, :mitarbeiter, :aktion, 0, :uid)'
        );
        $stmtIn->execute([
            ':user_id'     => $userId,
            ':zeitstempel' => date('Y-m-d H:i:s', $inTs),
            ':mitarbeiter' => $mitarbeiter,
            ':aktion'      => 'Check-In',
            ':uid'         => $uid,
        ]);

        $stmtOut = $pdo->prepare(
            'INSERT INTO arbeitszeiten (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
             VALUES (:user_id, :zeitstempel, :mitarbeiter, :aktion, :dauer, :uid)'
        );
        $stmtOut->execute([
            ':user_id'     => $userId,
            ':zeitstempel' => date('Y-m-d H:i:s', $outTs),
            ':mitarbeiter' => $mitarbeiter,
            ':aktion'      => 'Check-Out',
            ':dauer'       => $dauer,
            ':uid'         => $uid,
        ]);

        clocked_json([
            'success'       => true,
            'message'       => 'Manueller Eintrag gespeichert.',
            'today_seconds' => clocked_today_completed_seconds($pdo, $userId),
        ]);
    }

    if ($aktion === 'Check-In') {
        if ($clockedIn) {
            clocked_json(['success' => false, 'message' => 'Bereits eingecheckt.'], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO arbeitszeiten (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
             VALUES (:user_id, NOW(), :mitarbeiter, :aktion, 0, :uid)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':mitarbeiter' => $mitarbeiter,
            ':aktion'      => 'Check-In',
            ':uid'         => $uid,
        ]);

        $open = clocked_find_open_session($pdo, $userId);
        clocked_json([
            'success'     => true,
            'message'     => 'Check-In gespeichert.',
            'check_in_at' => $open['zeitstempel'] ?? null,
            'clocked_in'  => true,
        ]);
    }

    // Check-Out
    if (!$clockedIn || !$open) {
        $repairAt = trim($data['repair_check_in'] ?? '');
        if ($repairAt !== '') {
            $repairTs = strtotime($repairAt);
            if ($repairTs !== false && $repairTs <= time()) {
                $stmtRepair = $pdo->prepare(
                    'INSERT INTO arbeitszeiten (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
                     VALUES (:user_id, :zeitstempel, :mitarbeiter, :aktion, 0, :uid)'
                );
                $stmtRepair->execute([
                    ':user_id'     => $userId,
                    ':zeitstempel' => date('Y-m-d H:i:s', $repairTs),
                    ':mitarbeiter' => $mitarbeiter,
                    ':aktion'      => 'Check-In',
                    ':uid'         => $uid,
                ]);
                $open = clocked_find_open_session($pdo, $userId);
                $clockedIn = $open !== null;
            }
        }
    }

    if (!$clockedIn || !$open) {
        clocked_json(['success' => false, 'message' => 'Nicht eingecheckt.'], 409);
    }

    $checkInTs = strtotime((string) $open['zeitstempel']);
    if ($checkInTs === false) {
        clocked_json(['success' => false, 'message' => 'Ungültiger Check-In.'], 500);
    }

    $von = clocked_normalize_hm(trim($data['von'] ?? ''));
    $bis = clocked_normalize_hm(trim($data['bis'] ?? ''));

    if ($von !== null && $bis !== null) {
        $baseDate = date('Y-m-d', $checkInTs);
        $outTs = strtotime($baseDate . ' ' . $bis . ':00');
        $inTsAdj = strtotime($baseDate . ' ' . $von . ':00');
        if ($outTs === false || $inTsAdj === false) {
            clocked_json(['success' => false, 'message' => 'Ungültige Zeiten.'], 422);
        }
        if ($outTs < $inTsAdj) {
            $outTs += 86400;
        }
        $dauer = max(0, $outTs - $inTsAdj);
        $zeitstempel = date('Y-m-d H:i:s', $outTs);
    } else {
        $dauer = max(0, time() - $checkInTs);
        $zeitstempel = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO arbeitszeiten (user_id, zeitstempel, mitarbeiter, aktion, dauer_sekunden, uid)
         VALUES (:user_id, :zeitstempel, :mitarbeiter, :aktion, :dauer, :uid)'
    );
    $stmt->execute([
        ':user_id'     => $userId,
        ':zeitstempel' => $zeitstempel,
        ':mitarbeiter' => $mitarbeiter,
        ':aktion'      => 'Check-Out',
        ':dauer'       => $dauer,
        ':uid'         => $uid,
    ]);

    clocked_json([
        'success'       => true,
        'message'       => 'Check-Out gespeichert.',
        'dauer_sekunden'=> $dauer,
        'today_seconds' => clocked_today_completed_seconds($pdo, $userId),
    ]);
}

clocked_json(['success' => false, 'message' => 'Method not allowed'], 405);
