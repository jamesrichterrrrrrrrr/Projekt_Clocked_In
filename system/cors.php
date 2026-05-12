<?php
/**
 * Erlaubt Aufrufe von http://localhost / 127.0.0.1 (beliebiger Port) zu Infomaniak-PHP.
 * Nur nötig, wenn statische Seiten lokal und API remote laufen.
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (
    $origin !== ''
    && preg_match('#\Ahttps?://(localhost|127\.0\.0\.1)(:\d+)?\z#', $origin)
) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function clocked_apply_cross_origin_session_cookie(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (
        $origin === ''
        || !preg_match('#\Ahttps?://(localhost|127\.0\.0\.1)(:\d+)?\z#', $origin)
    ) {
        return;
    }
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    }
}
