<?php
// login.php
require_once __DIR__ . '/../system/cors.php';

ini_set('session.cookie_httponly', '1');
clocked_apply_cross_origin_session_cookie();
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../system/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON body"]);
    exit;
}

$email    = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password) {
    echo json_encode(["status" => "error", "message" => "Email and password are required"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $email;

        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Login temporarily unavailable. Please try again later.",
    ]);
}
