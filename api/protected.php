<?php
// index.php (API that returns JSON about the logged-in user)
require_once __DIR__ . '/../system/cors.php';

ini_set('session.cookie_httponly', '1');
clocked_apply_cross_origin_session_cookie();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

echo json_encode([
    "status"  => "success",
    "user_id" => $_SESSION['user_id'],
    "email"   => $_SESSION['email'],
]);
