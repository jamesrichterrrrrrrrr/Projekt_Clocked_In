<?php
// config.php
$host = '225f75.myd.infomaniak.com';
$db   = '225f75_clocked_in';  // Change to your DB name
$user = '225f75_quake_it';   // Change to your DB user
$pass = 'v_8Bq06?&6m-SFq';       // Change to your DB pass if needed

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass);
    // Optional: Set error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage();
    exit;
}
?>