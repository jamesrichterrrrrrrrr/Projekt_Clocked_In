<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/bootstrap.php';

clocked_start_session();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $userId = clocked_require_user_id();
    $user = clocked_fetch_user($pdo, $userId);
    if (!$user) {
        clocked_json(['success' => false, 'message' => 'User not found'], 404);
    }
    clocked_json(['success' => true, 'user' => clocked_user_payload($user)]);
}

if ($method === 'PUT' || $method === 'POST') {
    $userId = clocked_require_user_id();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        clocked_json(['success' => false, 'message' => 'Invalid JSON body'], 400);
    }

    $firstname = trim($data['firstname'] ?? '');
    $lastname  = trim($data['lastname'] ?? '');
    $email     = trim($data['email'] ?? '');

    if ($firstname === '' || $lastname === '') {
        clocked_json(['success' => false, 'message' => 'Vor- und Nachname sind erforderlich.'], 422);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        clocked_json(['success' => false, 'message' => 'Ungültige E-Mail-Adresse.'], 422);
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE users SET firstname = :firstname, lastname = :lastname, email = :email
             WHERE id = :id'
        );
        $stmt->execute([
            ':firstname' => $firstname,
            ':lastname'  => $lastname,
            ':email'     => $email,
            ':id'        => $userId,
        ]);
        $_SESSION['email'] = $email;

        $user = clocked_fetch_user($pdo, $userId);
        clocked_json(['success' => true, 'user' => clocked_user_payload($user ?: [])]);
    } catch (PDOException $e) {
        if (($e->errorInfo[0] ?? '') === '23000') {
            clocked_json(['success' => false, 'message' => 'Diese E-Mail-Adresse ist bereits vergeben.'], 409);
        }
        clocked_json(['success' => false, 'message' => 'Speichern fehlgeschlagen.'], 500);
    }
}

clocked_json(['success' => false, 'message' => 'Method not allowed'], 405);
