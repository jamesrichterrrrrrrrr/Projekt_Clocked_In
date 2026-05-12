<?php
// logout.php
require_once __DIR__ . '/../system/cors.php';

ini_set('session.cookie_httponly', '1');
clocked_apply_cross_origin_session_cookie();
session_start();

$_SESSION = [];
session_destroy();

header('Content-Type: application/json');
echo json_encode(["status" => "success"]);
exit;