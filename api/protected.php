<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/bootstrap.php';

$userId = clocked_require_user_id();
$user = clocked_fetch_user($pdo, $userId);

if (!$user) {
    clocked_json(['error' => 'User not found'], 404);
}

clocked_json([
    'status'  => 'success',
    'user_id' => $userId,
    'email'   => $_SESSION['email'] ?? $user['email'],
    'user'    => clocked_user_payload($user),
]);
